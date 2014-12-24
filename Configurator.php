<?php

namespace icron\configurator;

use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\Object;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\Query;
use yii\helpers\Inflector;


class Configurator extends Object
{
    const FREEZE_LEFT = 'left';
    const FREEZE_RIGHT = 'right';

    /**
     * @var string unique configurator id.
     */
    public $id;
    /**
     * @var ActiveQueryInterface base active record query.
     */
    public $query;
    /**
     * @var array attributes which set additional query.
     * For example, following code makes join table profile for columns 'age' and 'weight':
     * ```php
     *  [
     *      [['age', 'weight'], function ($query, $filterValue, $filterModel) {
     *              $query->joinWith('profile');
     *          }
     *      ]
     *  ]
     * ```
     */
    public $queryAttributes = [];
    /**
     * @var array grid column configuration with additional fields.
     * ```php
     * [
     *     ['class' => SerialColumn::className()],
     *     [
     *         'class' => DataColumn::className(),
     *         'attribute' => 'name',
     *         'format' => 'text',
     *         'label' => 'Name',
     *         'freeze' => true,
     *     ],
     *     ['class' => CheckboxColumn::className()],
     * ]
     * ```
     * Column is not defined in the model.
     * ```php
     * [
     *     [
     *         'attribute' => 'full_name',
     *         'query' => function ($query, $filterValue, $filterModel) {
     *              if (trim($filterValue) !== '') {
     *                 $value = explode(' ', $filterValue);
     *                 $value[1] = isset($value[1]) ? $value[1] : '';
     *                 $query->andWhere(['or', ['like', 'first_name', $value[0]], ['like', 'last_name', $value[1]]);
     *              }
     *         },
     *         'sort' =>  [
     *              'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
     *              'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_ASC],
     *         ],
     *         'rules' => ['string' , 'max' => 64],
     *         'content' => function($model, $key, $index, $column){
     *              return $model->first_name . ' ' . $model->last_name;
     *         }
     *     ],
     * ]
     * ```
     * Column should contain 'attribute' or 'name'. If the 'name' is not specified,
     * it will be received with the replacement of the 'attribute' '.' to '_'.
     * The 'name' element is required to identify in the grid.
     * If the value 'attribute' contains '.', then this attribute will be added relations using ActiveQuery::joinWith().
     * Column may also contain follows optional keys:
     * - the 'freeze' key, (with values 'left' or 'right'), indicates whether column frozen or not.
     * If 'freeze' equal 'left' then the column will be added after all the left frozen columns
     * else frozen to the end(after all columns).
     * - the 'rule' key, array,
     * - the 'sort' key, array,
     * - the 'query' key, Closure,
     * - the 'filter' key, mixed,
     * and other properties defined in the class column.
     * @see GridView::$columns
     */
    public $columns = [];
    /**
     * @var array list of attributes that are allowed to be sorted. Its syntax can be
     * described using the following example:
     * ```php
     * [
     *     'age',
     *     'name' => [
     *         'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
     *         'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
     *         'default' => SORT_DESC,
     *         'label' => 'Name',
     *     ],
     * ]
     * ```
     * In the above, two attributes are declared: 'age' and 'name'. The 'age' attribute is
     * a simple attribute which is equivalent to the following:
     * ```php
     * 'age' => [
     *     'asc' => ['age' => SORT_ASC],
     *     'desc' => ['age' => SORT_DESC],
     *     'default' => SORT_ASC,
     *     'label' => Inflector::camel2words('age'),
     * ]
     * ```
     * The 'name' attribute is a composite attribute:
     * - The 'name' key represents the attribute name which will appear in the URLs leading
     *   to sort actions.
     * - The 'asc' and 'desc' elements specify how to sort by the attribute in ascending
     *   and descending orders, respectively. Their values represent the actual columns and
     *   the directions by which the data should be sorted by.
     * - The 'default' element specifies by which direction the attribute should be sorted
     *   if it is not currently sorted (the default value is ascending order).
     * - The 'label' element specifies what label should be used when calling [[link()]] to create
     *   a sort link. If not set, [[Inflector::camel2words()]] will be called to get a label.
     *   Note that it will not be HTML-encoded.
     */
    public $sort = [];
    /**
     * Validation rules for attributes.
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     * Each rule is an array with the following structure:
     * ```php
     * [
     *     ['attribute1', 'attribute2'],
     *     'validator type',
     *     'on' => ['scenario1', 'scenario2'],
     *     ...other parameters...
     * ]
     * ```php
     * where
     *  - attribute list: required, specifies the attributes array to be validated, for single attribute you can pass string;
     *  - validator type: required, specifies the validator to be used. It can be a built-in validator name,
     *    a method name of the model class, an anonymous function, or a validator class name.
     *  - on: optional, specifies the [[scenario|scenarios]] array when the validation
     *    rule can be applied. If this option is not set, the rule will apply to all scenarios.
     *  - additional name-value pairs can be specified to initialize the corresponding validator properties.
     *    Please refer to individual validator class API for possible properties.
     * A validator can be either an object of a class extending [[Validator]], or a model class method
     * (called *inline validator*) that has the following signature:
     * ```
     * // $params refers to validation parameters given in the rule
     * function validatorName($attribute, $params)
     * ```
     * In the above `$attribute` refers to currently validated attribute name while `$params` contains an array of
     * validator configuration options such as `max` in case of `string` validator. Currently validate attribute value
     * can be accessed as `$this->[$attribute]`.
     * Yii also provides a set of [[Validator::builtInValidators|built-in validators]].
     * They each has an alias name which can be used when specifying a validation rule.
     * Below are some examples:
     * ```
     * [
     *     // built-in "required" validator
     *     [['username', 'password'], 'required'],
     *     // built-in "string" validator customized with "min" and "max" properties
     *     ['username', 'string', 'min' => 3, 'max' => 12],
     *     // built-in "compare" validator that is used in "register" scenario only
     *     ['password', 'compare', 'compareAttribute' => 'password2', 'on' => 'register'],
     *     // an inline validator defined via the "authenticate()" method in the model class
     *     ['password', 'authenticate', 'on' => 'login'],
     *     // a validator of class "DateRangeValidator"
     *     ['dateRange', 'DateRangeValidator'],
     * ];
     * ```
     * @see Model::rules()
     */
    public $rules = [];
    /**
     * @var string filter form name
     */
    public $filterFormName = 'DynamicModel';
    /**
     * @var array input data with following structure:
     * ```php
     * [
     *      'filterFormName' => [
     *          'first_name' => 'Jone',
     *          'last_name' => 'Doe'
     *      ]
     * ]
     * ```
     * May be passed $_REQUEST values.
     * @see filterModel
     */
    public $inputData = [];

    private $_columns = [];
    private $_sort = [];
    private $_query = [];
    private $_join = [];
    /** @var  DynamicModel */
    private $_filterModel;
    private $_model;

    /**
     * Initialize Configurator
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if ($this->query instanceof Query) {
            $this->_model = new $this->query->modelClass;
        }

        $this->_filterModel = $this->initFilterModel();
        $this->_filterModel->load($this->inputData);
        $this->_filterModel->validate();

        foreach ($this->columns as $column) {
            $this->addColumn($column);
        }
        $this->_sort = array_merge($this->_sort, $this->sort);

        foreach ($this->queryAttributes as $queryAttribute) {
            if (isset($queryAttribute[0], $queryAttribute[1]) && is_array($queryAttribute[0])) {
                foreach ($queryAttribute[0] as $name) {
                    if (isset($this->_columns[$name])) {
                        $this->_query[] = $queryAttribute[1];
                    }
                }
            } else {
                throw new InvalidConfigException(
                    'Invalid query attributes: first element must be an array and second element must be instance of Closure.'
                );
            }
        }
    }

    /**
     * Adds column.
     * @param array $column the configuration of the column.
     * See $columns, where each element is the configuration of the column.
     * @throws \yii\base\InvalidConfigException
     * @return bool Column is added
     */
    public function addColumn(array $column)
    {
        if (isset($column['visible']) && !$column['visible']) {
            return false;
        }
        if (!isset($column['name']) && !isset($column['attribute'])) {
            throw new InvalidConfigException("Column must be contain 'name' or 'attribute' option.");
        } else {
            $columnName = isset($column['name']) ? $column['name'] : str_replace('.', '_', $column['attribute'], $hasRelations);
            if (in_array($columnName, $this->getColumnsName($this->_columns))) {
                throw new InvalidConfigException('Duplicate attribute name: ' . $columnName);
            }
            $column['name'] = $columnName;

            // relation based on attribute values
            if (isset($hasRelations) && $hasRelations) {
                $this->_query[] = function (ActiveQuery $query) use ($column) {
                    if (!in_array($column['attribute'], $this->_join)) {
                        $query->joinWith($column['attribute']);
                    }
                    $this->_join[] = $column['attribute'];
                };
            }
        }

        // add column
        $countColumns = count($this->_columns);
        if (isset($column['freeze'])) {
            if ($column['freeze'] == self::FREEZE_LEFT) {
                for ($i = 0; $i < $countColumns; $i++) {
                    if (!isset($this->_columns[$i]['freeze']) || $this->_columns[$i]['freeze'] != self::FREEZE_LEFT) {
                        break;
                    }
                }
                array_splice($this->_columns, $i, 0, $column);
            } else {
                array_push($this->_columns, $column);
            }
        } else {
            $resultPos = $countColumns;
            $input = $this->_filterModel->columns ? array_map('trim', explode(',', $this->_filterModel->columns)) : [];
            $columnPos = array_search($column['name'], $input);
            if ($columnPos === false) {
                return false;
            }
            for ($i = $countColumns - 1; $i >= 0; $i--) {
                if (!isset($this->_columns[$i]['freeze'])) {
                    $currentPos = array_search($this->_columns[$i]['name'], $input);
                    if ($currentPos < $columnPos) {
                        $resultPos = $i;
                        break;
                    }
                } elseif ($this->_columns[$i]['freeze'] == self::FREEZE_LEFT) {
                    $resultPos = $i;
                }

            }
            array_splice($this->_columns, $resultPos + 1, 0, $column);
        }

        // add sort
        if (isset($column['sort'])) {
            $this->_sort[$columnName] = $column['sort'];
            unset($column['sort']);
        }

        // add query
        if (isset($column['query'])) {
            $this->_query[$columnName][] = $column['query'];
            unset($column['query']);
        }

        // add in filter model
        if (!empty($column['rule'])) {
            $this->_filterModel->defineAttribute($columnName);
            $this->_filterModel->addRule([$columnName], $column['rule'][0], array_slice($column['rule'], 1));
        }
        unset($column['rule']);

        //add model label
        if (!isset($column['label']) || trim($column['label']) === '') {
            if (isset($column['header']) && preg_match('#^[\w\s]+$#ui', $column['header'])) {
                $label = $column['header'];
            } elseif ($this->query instanceof ActiveQueryInterface) {
                /** @var Model $model */
                $model = new $this->query->modelClass;
                $label = $model->getAttributeLabel($columnName);
            } else {
                $label = Inflector::camel2words($columnName);
            }
        } else {
            $label = $column['label'];
        }
        $this->_filterModel->labels[$columnName] = $label;

        return true;
    }

    /**
     * Gets sort configuration.
     * @return array sort configuration
     */
    public function getSort()
    {
        return $this->_sort;
    }

    /**
     * Gets sorted and filtered columns configuration without extra indexes ('freeze', 'name').
     * If you want to get columns configuration with all indexes use getRawColumns() function.
     * @return array
     * @see getRawColumns
     */
    public function getCleanColumns()
    {
        $result = [];
        foreach ($this->_columns as $column) {
            unset($column['name'], $column['freeze']);
        }

        return $result;
    }


    /**
     * Gets sorted and filtered columns configuration.
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * Gets frozen columns.
     * @return array frozen columns
     */
    public function getFreezeColumns()
    {
        return array_filter(
            $this->_columns,
            function ($item) {
                return isset($item['freeze']);
            }
        );
    }

    /**
     * Gets columns label with following stricture:
     * ```php
     * ['attribute1' => 'label1', 'attribute2' => 'label2']
     * ```php
     * @return array Associative array of labels column.
     */
    public function getColumnsLabel()
    {
        return $this->_filterModel->labels;
    }

    /**
     * Gets columns name.
     * @return array List of names column.
     */
    protected function getColumnsName()
    {
        return array_map(
            function ($item) {
                return $item['name'];
            },
            $this->_columns
        );
    }

    /**
     * Gets calculated query.
     * @return mixed
     */
    public function getQuery()
    {
        $query = $this->query;
        $filterModel = $this->getFilterModel();
        foreach ($this->_query as $attribute => $queryClosures) {
            $filterValue = isset($filterModel->attributes[$attribute]) ? $filterModel->{$attribute} : null;
            foreach ($queryClosures as $queryClosure) {
                $query = call_user_func($queryClosure, $query, $filterValue, $filterModel);
            }
        }

        return $query;
    }

    /**
     * Gets filter model.
     * The model is already filled and validated.
     * @return DynamicModel
     */
    public function getFilterModel()
    {
        return $this->_filterModel;
    }

    /**
     * Initializes the filter model.
     * Note that columns without rules will be ignored
     * @return DynamicModel
     */
    protected function initFilterModel()
    {
        $attributes = [];
        foreach ($this->rules as $rule) {
            if (is_string($rule[0])) {
                $attributes[] = $rule[0];
            } elseif (is_array($rule[0])) {
                $attributes = array_merge($attributes, $rule[0]);
            }
            $attributes = array_unique($attributes);
        }
        $model = DynamicModel::validateData($attributes, $this->rules);
        $model->defineAttribute('columns');
        $model->addRule('columns', 'match', ['pattern' => '#[-\w+\s]*#i']);
        foreach ($attributes as $attribute) {
            $model->labels[$attribute] = Inflector::camel2words($attribute);
        }
        $model->formName = $this->filterFormName;

        return $model;
    }

    /**
     * Gets ActiveDataProvider configuration.
     * @param array $config more ActiveDataProvider configuration, which will be merged with the main configuration
     * @return array ActiveDataProvider configuration
     */
    public function getActiveDataProviderConfig($config = [])
    {
        return array_merge(
            [
                'query' => $this->getQuery(),
                'sort' => ['attributes' => $this->getSort()],
            ],
            $config
        );
    }

    /**
     * Gets GridView configuration.
     * @param array $config more GridView configuration, which will be merged with the main configuration
     * @return array GridView configuration
     */
    public function getGridViewConfig($config = [])
    {
        return array_merge(
            [
                'dataProvider' =>
                    isset($config['dataProvider']) ? '' : new ActiveDataProvider($this->getActiveDataProviderConfig()),
                'filterModel' => $this->getFilterModel(),
                'columns' => $this->getCleanColumns(),
            ],
            $config
        );
    }
}