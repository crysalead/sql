<?php
namespace sql;

use stdClass;
use set\Set;
use text\Text;
use sql\SqlException;

/**
 * ANSI SQL dialect
 */
class Dialect
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Defaults internal type matching.
     *
     * @var array
     */
     protected $_matches = [];

    /**
     * The quoter handler.
     *
     * @var Closure
     */
    protected $_quoter = null;

    /**
     * The casting handler.
     *
     * @var Closure
     */
    protected $_caster = null;

    /**
     * Quoting identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

    /**
     * Column type definitions.
     *
     * @var array
     */
    protected $_types = [];

    /**
     * List of SQL operators, paired with handling options.
     *
     * @var array
     */
    protected $_operators = [
        '='            => ['null' => ':is'],
        '<=>'          => [],
        '<'            => [],
        '>'            => [],
        '<='           => [],
        '>='           => [],
        '!='           => ['null' => ':is not'],
        '<>'           => [],
        '-'            => [],
        '+'            => [],
        '*'            => [],
        '/'            => [],
        '%'            => [],
        '>>'           => [],
        '<<'           => [],
        ':='           => [],
        '&'            => [],
        '|'            => [],
        ':mod'         => [],
        ':div'         => [],
        ':like'        => [],
        ':not like'    => [],
        ':is'          => [],
        ':is not'      => [],
        ':distinct'    => ['builder' => 'prefix'],
        '~'            => ['builder' => 'prefix'],
        ':between'     => ['builder' => 'between'],
        ':not between' => ['builder' => 'between'],
        ':in'          => ['builder' => 'list'],
        ':not in'      => ['builder' => 'list'],
        ':exists'      => ['builder' => 'list'],
        ':not exists'  => ['builder' => 'list'],
        ':all'         => ['builder' => 'list'],
        ':any'         => ['builder' => 'list'],
        ':some'        => ['builder' => 'list'],
        ':as'          => ['builder' => 'alias'],
        // logical operators
        ':not'         => ['builder' => 'prefix'],
        ':and'         => [],
        ':or'          => [],
        ':xor'         => [],
        '()'           => ['format' => '(%s)']
    ];

    /**
     * Operator builders
     *
     * @var array
     */
    protected $_builders = [];

    /**
     * List of formatter operators
     *
     * @var array
     */
    protected $_formatters = [];

    /**
     * Date format
     *
     * @var string
     */
    protected $_dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param array $config The config array
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'select'       => 'sql\statement\Select',
                'insert'       => 'sql\statement\Insert',
                'update'       => 'sql\statement\Update',
                'delete'       => 'sql\statement\Delete',
                //'create table' => 'sql\statement\CreateTable',
                'drop table'   => 'sql\statement\DropTable'
            ],
            'quoter' => null,
            'caster' => null,
            'types' => [],
            'operators' => [],
            'builders' => $this->_builders(),
            'formatters' => $this->_formatters(),
            'dateFormat' => 'Y-m-d H:i:s'
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_quoter = $config['quoter'];
        $this->_caster = $config['caster'];
        $this->_dateFormat = $config['dateFormat'];
        $this->_types = $config['types'] + $this->_types;
        $this->_builders = $config['builders'] + $this->_builders;
        $this->_formatters = $config['formatters'] +$this->_formatters;
        $this->_operators = $config['operators'] + $this->_operators;
    }

    /**
     * Return default operator builders
     *
     * @return array
     */
    protected function _builders()
    {
        return [
            'function' => function ($operator, $parts) {
                $operator = strtoupper(substr($operator, 0, -2));
                return "{$operator}(" . join(", ", $parts). ')';
            },
            'prefix' => function ($operator, $parts) {
                return "{$operator} " . reset($parts);
            },
            'list' => function ($operator, $parts) {
                $key = array_shift($parts);
                return "{$key} {$operator} (" . join(", ", $parts) . ')';
            },
            'between' => function ($operator, $parts) {
                $key = array_shift($parts);
                return "{$key} {$operator} " . reset($parts) . ' AND ' . end($parts);
            },
            'set' => function ($operator, $parts) {
                return join(" {$operator} ", $parts);
            },
            'alias' => function ($operator, $parts) {
                $expr = array_shift($parts);
                return "({$expr}) {$operator} " . array_shift($parts);
            }
        ];
    }

    /**
     * Return default formatters.
     *
     * @return array
     */
    protected function _formatters()
    {
        return [
            ':name' => function ($value, $states) {
                return $this->name($value);
            },
            ':value' => function ($value, $states) {
                return $this->value($value, $states);
            },
            ':plain' => function ($value, $states) {
                return (string) $value;
            }
        ];
    }

    /**
     * Gets/sets the quoter handler
     *
     * @param  Closure $quoter The quoter handler.
     * @return Closure         Returns the quoter handler.
     */
    public function quoter($quoter = null)
    {
        if ($quoter !== null) {
            $this->_quoter = $quoter;
        }
        return $this->_quoter;
    }

    /**
     * Gets/sets the casting handler
     *
     * @param  Closure $caster The casting handler.
     * @return Closure         Returns the casting handler.
     */
    public function caster($caster = null)
    {
        if ($caster !== null) {
            $this->_caster = $caster;
        }
        return $this->_caster;
    }

    /**
     * Gets/sets an internal type definition
     *
     * @param  string $type   The type name.
     * @param  array  $config The type definition.
     * @return array          Return the type definition.
     */
    public function type($type, $config = null)
    {
        if ($config) {
            $this->_types[$type] = $config;
        }
        if (!isset($this->_types[$type])) {
            throw new SqlException("Column type `'{$type}'` does not exist.");
        }
        return $this->_types[$type];
    }

    /**
     * Gets/sets a type matching.
     *
     * @param  string $type   The type name.
     * @param  array  $config The type definition.
     * @return array          Return the type definition.
     */
    public function typeMatch($use, $type = null)
    {
        if ($type) {
            $this->_matches[$use] = $type;
        }
        if (!isset($this->_matches[$use])) {
            throw new SqlException("No type matching has been defined for `'{$use}'`.");
        }
        return $this->_matches[$use];
    }

    /**
     * Formats a field definition
     *
     * @param  array $field   A partial field definition.
     * @return array          A complete field definition.
     */
    public function field($field)
    {
        if (!isset($field['name'])) {
            throw new SqlException("Missing column name.");
        }
        if (isset($field['type'])) {
            $field += $this->type($field['type']);
        } elseif (!isset($field['use'])) {
            $field += $this->type('string');
        }
        return $field + [
            'name'      => null,
            'type'      => null,
            'length'    => null,
            'precision' => null,
            'serial'    => false,
            'default'   => null,
            'null'      => null
        ];
    }

    /**
     * SQL Statement factory
     *
     * @param string $name The name of the statement to instantiate.
     * @param
     */
    public function statement($name, $config = [])
    {
        $defaults = ['dialect' => $this];
        $config += $defaults;

        if (!isset($this->_classes[$name])) {
            throw new SqlException("Unsupported statement `'{$name}'`.");
        }
        $statement = $this->_classes[$name];
        return new $statement($config);
    }

    /**
     * Generates a list of escaped table/field names identifier.
     */
    public function names($fields)
    {
        return (string) join(", ", $this->escapes(is_array($fields) ? $fields : [$fields], ''));
    }

    /**
     * Escapes a list of identifers.
     *
     * Note: it ignores duplicates.
     *
     */
    public function escapes($names, $prefix)
    {
        $names = is_array($names) ? $names : [$names];
        $sql = [];
        foreach ($names as $key => $value) {
            if ($this->isOperator($key)) {
                $sql[] = $this->conditions($names);
            } elseif (is_string($value)) {
                if (!is_numeric($key)) {
                    $name = $this->name($key);
                    $value = $this->name($value);
                    $name = $name !== $value ? "{$name} AS {$value}" : $name;
                } else {
                    $name = $this->name($value);
                }
                $name = $prefix ? "{$prefix}.{$name}" : $name;
                $sql[$name] = $name;
            } elseif (!is_array($value)) {
                $sql[] = (string) $value;
            } else {
                $pfx = $prefix;
                if (!is_numeric($key)) {
                    $pfx = $this->escape($key);
                }
                $sql = array_merge($sql, $this->escapes($value, $pfx));
            }
        }
        return $sql;
    }

    public function prefix($data, $prefix)
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($this->isOperator($key)) {
                if ($key === ':name') {
                    $value = $this->_prefix($value, $prefix);
                }
                if (!is_array($value)) {
                    $result[$key] = $value;
                    continue;
                }
            }
            if (is_array($value)) {
                $result[$key] = $this->prefix($value, $prefix);
                continue;
            }
            if (is_numeric($key)) {
                $value = $this->_prefix($value, $prefix);
            } else {
                $key = $this->_prefix($key, $prefix);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    public function _prefix($name, $prefix)
    {
        list($alias, $field) = $this->undot($name);
        return $alias ? $name : "{$prefix}.{$field}";
    }

    /**
     * Returns a string of formatted conditions to be inserted into the query statement. If the
     * query conditions are defined as an array, key pairs are converted to SQL strings.
     *
     * Conversion rules are as follows:
     *
     * - If `$key` is numeric and `$value` is a string, `$value` is treated as a literal SQL
     *   fragment and returned.
     *
     * @param  string|array $conditions The conditions for this query.
     * @param  array        $options    - `prepend` mixed: The string to prepend or false for
     *                                  no prepending.
     * @return string                   Returns an SQL conditions clause.
     */
    public function conditions($conditions, $options = [])
    {
        if (!$conditions) {
            return '';
        }
        $defaults = ['prepend' => false, 'operator' => ':and', 'schemas' => []];
        $options += $defaults;

        if (!is_numeric(key($conditions))) {
            $conditions = [$conditions];
        }

        $states = $options + [
            'schemas' => [],
            'schema' => null,
            'name' => null,
        ];

        $result = $this->_operator(strtolower($options['operator']), $conditions, $states);
        return ($options['prepend'] && $result) ? "{$options['prepend']} {$result}" : $result;
    }

    /**
     * Build a SQL operator statement.
     *
     * @param  string $operator   The operator.
     * @param  mixed  $conditions The data for the operator.
     * @return string              Returns a SQL string.
     */
    protected function _operator($operator, $conditions, $states)
    {
        if (substr($operator, -2) === '()') {
            $config = ['builder' => 'function'];
        } else if (isset($this->_operators[$operator])) {
            $config = $this->_operators[$operator];
        } else {
            throw new SqlException("Unexisting operator `'{$operator}'`.");
        }

        $parts = $this->_conditions($conditions, $states);

        $operator = (is_array($parts) && next($parts) === null && isset($config['null'])) ? $config['null'] : $operator;
        $operator = $operator[0] === ':' ? strtoupper(substr($operator, 1)) : $operator;

        if (!isset($config['builder'])) {
            return join(" {$operator} ", $parts);
        }
        $builder = $this->_builders[$config['builder']];
        return $builder($operator, $parts);
    }

    public function isOperator($operator)
    {
        return ($operator && $operator[0] === ':') || isset($this->_operators[$operator]);
    }

    /**
     * Build a formated array of SQL statement.
     *
     * @param  string $key    The field name.
     * @param  mixed  $value  The data value.
     * @return array          Returns a array of SQL string.
     */
    protected function _conditions($conditions, $states)
    {
        $parts = [];
        foreach ($conditions as $name => $value) {
            $operator = strtolower($name);
            if (isset($this->_formatters[$operator])) {
                $parts[] = $this->format($operator, $value, $states);
            } elseif ($this->isOperator($operator)) {
                $parts[] = $this->_operator($operator, $value, $states);
            } elseif (is_numeric($name)) {
                if (is_array($value)) {
                    $parts = array_merge($parts, $this->_conditions($value, $states));
                } else {
                    $parts[] = $this->value($value, $states);
                }
            } else {
                $parts[] = $this->_name($name, $value, $states);
            }
        }
        return $parts;
    }

    /**
     * Build a <fieldname> = <value> SQL condition.
     *
     * @param  string $name    The field name.
     * @param  mixed  $value  The data value.
     * @return string         Returns a SQL string.
     */
    protected function _name($name, $value, &$states)
    {
        list($alias, $field) = $this->undot($name);
        $escaped = $this->name($name);
        $schema = isset($states['schemas'][$alias]) ? $states['schemas'][$alias] : null;
        $states['name'] = $field;
        $states['schema'] = $schema;

        if (!is_array($value)) {
            return "{$escaped} = " . $this->value($value, $states);
        }

        $operator = strtolower(key($value));
        if (isset($this->_formatters[$operator])) {
            return "{$escaped} = " . $this->format($operator, current($value), $states);
        } elseif (!isset($this->_operators[$operator])) {
            return $this->_operator(':in', [[':name' => $name], $value], $states);
        }

        $conditions = current($value);
        $conditions = (array) $conditions;
        array_unshift($conditions, [':name' => $name]);
        return $this->_operator($operator, $conditions, $states);
    }

    /**
     * SQL formatter.
     *
     * @param  string $operator The format operator.
     * @param  mixed  $value    The value to format.
     * @param  string $type     The value type.
     * @return string           Returns a SQL string.
     */
    public function format($operator, $value, $states = [])
    {
        if (!isset($this->_formatters[$operator])) {
            throw new SqlException("Unexisting formatter `'{$operator}'`.");
        }
        $formatter = $this->_formatters[$operator];
        return $formatter($value, $states);
    }

    /**
     * Escapes a column/table/schema with dotted syntax support.
     *
     * @param  string $name  Identifier name.
     * @param  string $alias The filled alias name if present.
     * @return string        The escaped identifien.
     */
    public function name($name)
    {
        if (!is_string($name)) {
            return $this->names($name);
        }
        list($alias, $field) = $this->undot($name);
        return $alias ? $this->escape($alias) . '.' . $this->escape($field) : $this->escape($name);
    }

    /**
     * Escapes a column/table/schema name.
     *
     * @param  string $name Identifier name.
     * @return string
     */
    public function escape($name)
    {
        return $name === '*' ? '*' : $this->_escape . $name . $this->_escape;
    }

    /**
     * Split dotted syntax into distinct name.
     *
     * @param  string $field A dotted identifier.
     * @return array
     */
    public function undot($field)
    {
        if (is_string($field) && (($pos = strrpos($field, ".")) !== false)) {
            return [substr($field, 0, $pos), substr($field, $pos + 1)];
        }
        return [null, $field];
    }

    /**
     * Quote a string.
     *
     * @param  string $string The string to quote.
     * @return string
     */
    public function quote($string)
    {
        if ($quoter = $this->quoter()) {
            return $quoter($string);
        }
        $replacements = array(
            "\x00"=>'\x00',
            "\n"=>'\n',
            "\r"=>'\r',
            "\\"=>'\\\\',
            "'"=>"\'",
            "\x1a"=>'\x1a'
        );
        return "'" . strtr(addcslashes($string, '%_'), $replacements) . "'";
    }

    /**
     * Converts a given value into the proper type based on a given schema definition.
     *
     * @param  mixed  $value The value to be converted. Arrays will be recursively converted.
     * @param  string $type  The value type.
     * @return mixed         The formatted value.
     */
    public function value($value, $states = [])
    {
        if ($caster = $this->caster()) {
            return $caster($value, $states);
        }
        switch (true) {
            case is_bool($value):
                return $value ? 'TRUE' : 'FALSE';
            case is_string($value):
                return $this->quote($value);
            case is_array($value):
                $cast = function($value) use (&$cast) {
                    $result = [];
                    foreach ($value as $k => $v) {
                        if (is_array($v)) {
                            $result[] = $cast($v);
                        } else {
                            $result[] = $v;
                        }
                    }
                    return '{' . join(',', $result) . '}';

                };
                return $cast($value);
        }
        return (string) $value;
    }

    /**
     * Generate a database-native column schema string
     *
     * @param  array  $column A field array structured like the following:
     *                        `['name' => 'value', 'type' => 'value' [, options]]`, where options
     *                        can be `'default'`, `'null'`, `'length'` or `'precision'`.
     * @return string         A SQL string formated column.
     */
    public function column($field)
    {
        $field = $this->field($field);

        $isNumeric = preg_match('/^(integer|float|boolean)$/', $field['type']);
        if ($isNumeric && $field['default'] === '') {
            $field['null'] = true;
            $field['default'] = null;
        }
        $field['use'] = strtolower($field['use']);
        return $this->_column($field);
    }

    /**
     * Builds a column/table meta.
     *
     * @param  array  $data  The meta data.
     * @param  array  $names If `$names` is not `null` only build meta present in `$names`
     * @return string        The SQL meta
     */
    public function meta($type, $data, $names = null)
    {
        $result = [];
        $names = $names ? (array) $names : array_keys($data);
        foreach ($names as $name) {
            $value = isset($data[$name]) ? $data[$name] : null;
            if ($value && $meta = $this->_meta($type, $name, $value)) {
                $result[] = $meta;
            }
        }
        return join(' ', $result);
    }

    /**
     * Helper for building a column/table single meta string.
     *
     * @param  string $type  The type of the meta to build (possible values: 'table' or 'column')
     * @param  string $name  The name of the meta to build
     * @param  mixed  $value The value used for building the meta
     * @return string        The SQL meta string
     */
    protected function _meta($type, $name, $value)
    {
        $meta = isset($this->_meta[$type][$name]) ? $this->_meta[$type][$name] : null;
        if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
            return;
        }
        $meta += ['keyword' => '', 'escape' => false, 'join' => ' '];
        extract($meta);
        if ($escape === true) {
            $value = $this->value($value);
        }
        $result = $keyword . $join . $value;
        return $result !== ' ' ? $result : '';
    }

    /**
     * Build a SQL column constraint
     *
     * @param  string $name  The name of the meta to build.
     * @param  mixed  $value The value used for building the meta.
     * @return string        The SQL meta string.
     */
    public function constraint($name, $value, $options = [])
    {
        $value += ['options' => []];
        $meta = isset($this->_constraints[$name]) ? $this->_constraints[$name] : null;
        if (!($template = isset($meta['template']) ? $meta['template'] : null)) {
            throw new SqlException("Invalid constraint template `'{$name}'`.");
        }

        $data = [];
        foreach ($value as $name => $value) {
            switch ($name) {
                case 'key':
                case 'index':
                    if (isset($meta[$name])) {
                        $data['index'] = $meta[$name];
                    }
                break;
                case 'to':
                    $data[$name] = $this->name($value);
                break;
                case 'on':
                    $data[$name] = "ON {$value}";
                break;
                case 'constraint':
                    $data[$name] = "CONSTRAINT " . $this->name($value);
                break;
                case 'expr':
                    $data[$name] = $this->conditions(is_array($value) ? $value : [$value], $options);
                break;
                case 'column':
                case 'primaryKey';
                case 'foreignKey';
                    $data[$name] = join(', ', array_map([$this, 'name'], (array) $value));
                break;
            }
        }

        return trim(Text::insert($template, $data, ['clean' => ['method' => 'text']]));
    }
}