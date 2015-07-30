<?php
namespace sql\dialect;

use set\Set;

/**
 * MySQL dialect.
 */
class MySql extends \sql\Dialect
{
    /**
     * MySQL types and their associatied internal types.
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
     * @var array
     */
    protected $_escape = '`';

    /**
     * Meta attribute syntax pattern.
     *
     * Note: by default `'escape'` is false and 'join' is `' '`.
     *
     * @var array
     */
    protected $_meta = [
        'column' => [
            'charset' => ['keyword' => 'CHARACTER SET'],
            'collate' => ['keyword' => 'COLLATE'],
            'comment' => ['keyword' => 'COMMENT', 'escape' => true]
        ],
        'table' => [
            'charset' => ['keyword' => 'DEFAULT CHARSET'],
            'collate' => ['keyword' => 'COLLATE'],
            'engine' => ['keyword' => 'ENGINE'],
            'tablespace' => ['keyword' => 'TABLESPACE']
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
        'index' => ['template' => 'INDEX ({:column})'],
        'unique' => [
            'template' => 'UNIQUE {:index} ({:column})',
            'key' => 'KEY',
            'index' => 'INDEX'
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
                'select'       => 'sql\statement\mysql\Select',
                'insert'       => 'sql\statement\mysql\Insert',
                'update'       => 'sql\statement\mysql\Update',
                'delete'       => 'sql\statement\mysql\Delete',
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

        $this->type('id',       ['use' => 'int']);
        $this->type('serial',   ['use' => 'int', 'serial' => true]);
        $this->type('string',   ['use' => 'varchar', 'length' => 255]);
        $this->type('text',     ['use' => 'text']);
        $this->type('integer',  ['use' => 'int']);
        $this->type('boolean',  ['use' => 'boolean']);
        $this->type('float',    ['use' => 'float']);
        $this->type('decimal',  ['use' => 'decimal', 'precision' => 2]);
        $this->type('date',     ['use' => 'date']);
        $this->type('time',     ['use' => 'time']);
        $this->type('datetime', ['use' => 'datetime']);
        $this->type('binary',   ['use' => 'blob']);

        $this->map('bigint',             'integer');
        $this->map('bit',                'string');
        $this->map('blob',               'string');
        $this->map('char',               'string');
        $this->map('date',               'date');
        $this->map('datetime',           'datetime');
        $this->map('decimal',            'decimal');
        $this->map('double',             'float');
        $this->map('float',              'float');
        $this->map('geometry',           'string');
        $this->map('geometrycollection', 'string');
        $this->map('int',                'integer');
        $this->map('linestring',         'string');
        $this->map('longblob',           'string');
        $this->map('longtext',           'string');
        $this->map('mediumblob',         'string');
        $this->map('mediumint',          'integer');
        $this->map('mediumtext',         'string');
        $this->map('multilinestring',    'string');
        $this->map('multipolygon',       'string');
        $this->map('multipoint',         'string');
        $this->map('point',              'string');
        $this->map('polygon',            'string');
        $this->map('smallint',           'integer');
        $this->map('text',               'string');
        $this->map('time',               'string');
        $this->map('timestamp',          'datetime');
        $this->map('tinyblob',           'string');
        $this->map('tinyint',            'boolean', ['length' => 1]);
        $this->map('tinyint',            'integer');
        $this->map('tinytext',           'string');
        $this->map('varchar',            'string');
        $this->map('year',               'string');
    }

    /**
     * Helper for creating columns
     *
     * @see    chaos\source\sql\Dialect::column()
     * @param  array $field A field array
     * @return string The SQL column string
     */
    protected function _column($field)
    {
        extract($field);
        if ($type === 'float' && $precision) {
            $use = 'decimal';
        }

        $column = $this->name($name) . ' ' . $this->_formatColumn($use, $length, $precision);

        $result = [$column];
        $result[] = $this->meta('column', $field, ['charset', 'collate']);

        if (!empty($serial)) {
            $result[] = 'NOT NULL AUTO_INCREMENT';
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

        $result[] = $this->meta('column', $field, ['comment']);
        return join(' ', array_filter($result));
    }
}
