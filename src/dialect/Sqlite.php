<?php
namespace sql\dialect;

use set\Set;

/**
 * Sqlite dialect.
 */
class Sqlite extends \sql\Dialect
{
    /**
     * Sqlite types and their associatied internal types.
     *
     * @var array
     */
     protected $_maps = [];

    /**
     * Column type definitions.
     *
     * @var array
     */
    protected $_types = [];

    /**
     * Escape identifier character.
     *
     * @var string
     */
    protected $_escape = '"';

    /**
     * Meta attribute syntax pattern.
     *
     * Note: by default `'escape'` is false and 'join' is `' '`.
     *
     * @var array
     */
    protected $_meta = [
        'column' => [
            'collate' => ['keyword' => 'COLLATE', 'escape' => true]
        ]
    ];

    /**
     * Column contraints template
     *
     * @var array
     */
    protected $_constraints = [
        'primary' => ['template' => 'PRIMARY KEY ({:column})'],
        'foreign key' => [
            'template' => 'FOREIGN KEY ({:foreignKey}) REFERENCES {:to} ({:primaryKey}) {:on}'
        ],
        'unique' => [
            'template' => 'UNIQUE {:index} ({:column})'
        ],
        'check' => ['template' => '{:constraint} CHECK ({:expr})']
    ];

    /**
     * Constructor
     *
     * @param array $config The config array
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'select'       => 'sql\statement\sqlite\Select',
                'insert'       => 'sql\statement\sqlite\Insert',
                'update'       => 'sql\statement\sqlite\Update',
                'delete'       => 'sql\statement\sqlite\Delete',
                'create table' => 'sql\statement\CreateTable',
                'drop table'   => 'sql\statement\DropTable'
            ],
            'operators' => [
                '#'            => ['format' => '%s ^ %s'],
                ':regex'       => ['format' => '%s REGEXP %s'],
                ':rlike'       => [],
                ':sounds like' => [],
                // Algebraic operations
                ':union'       => ['builder' => 'set'],
                ':union all'   => ['builder' => 'set'],
                ':minus'       => ['builder' => 'set'],
                ':except'      => ['name' => 'MINUS', 'type' => 'set']
            ]
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);

        $this->type('id',       ['use' => 'integer']);
        $this->type('serial',   ['use' => 'integer', 'serial' => true]);
        $this->type('string',   ['use' => 'varchar', 'length' => 255]);
        $this->type('text',     ['use' => 'text']);
        $this->type('integer',  ['use' => 'integer']);
        $this->type('boolean',  ['use' => 'boolean']);
        $this->type('float',    ['use' => 'real']);
        $this->type('decimal',  ['use' => 'text', 'precision' => 2]);
        $this->type('date',     ['use' => 'date']);
        $this->type('time',     ['use' => 'time']);
        $this->type('datetime', ['use' => 'timestamp']);
        $this->type('binary',   ['use' => 'blob']);

        $this->map('boolean',   'boolean');
        $this->map('blob',      'binary');
        $this->map('date',      'date');
        $this->map('integer',   'integer');
        $this->map('numeric',   'decimal', ['precision' => 2]);
        $this->map('real',      'float');
        $this->map('text',      'text');
        $this->map('time',      'time');
        $this->map('timestamp', 'datetime');
        $this->map('varchar',   'string');
    }

    /**
     * Helper for creating columns
     *
     * @see    chaos\source\sql\Dialect::column()
     *
     * @param  array  $field A field array
     * @return string        The SQL column string
     */
    protected function _column($field)
    {
        extract($field);
        if ($type === 'float' && $precision) {
            $use = 'numeric';
        }

        $column = $this->name($name) . ' ' . $this->_formatColumn($use, $length, $precision);

        $result = [$column];
        $result[] = $this->meta('column', $field, ['collate']);

        if (!empty($serial)) {
            $result[] = 'NOT NULL';
        } else {
            $result[] = is_bool($null) ? ($null ? 'NULL' : 'NOT NULL') : '' ;
            if ($default !== null) {
                if (is_array($default)) {
                    list($operator, $default) = each($default);
                } else {
                    $operator = ':value';
                }
                $result[] = 'DEFAULT ' . $this->format($operator, $default, compact('field'));
            }
        }
        return join(' ', array_filter($result));
    }
}