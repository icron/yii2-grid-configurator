Yii2 GRID CONFIGURATOR
====================
Server-side implementation of the extended grid, which has a number of features:
  - moving columns
  - sorting columns
  - freeze columns
  - visibility of columns (depending on the visible columns, an appropriate SQL query)
  - filters columns (using DynamicModel)
  - custom page size
  
In short, adjust general configuration file for all columns:
  - rules(for filters)
  - sort
  - query (you can specify any complexity SQL query for each column group, join)

All of the functionality of the control grid can be taken in individual widgets.

For example:
```
    $configurator = new \icron\configurator\Configurator([
      'query' => \app\models\Reports::find(),
      'inputData' => $_POST,
      'columns' => [
          [
              'attribute' => 'id',
              'freeze' => true,
          ],
          [
              'attribute' => '_eee',
              'label' => 'Name',
              'content' => function ($model) {
                  return $model->epc;
              },
              'sort' => [
                  'asc' => ['epc' => SORT_ASC],
                  'desc' => ['epc' => SORT_DESC],
              ],
              'query' => function ($query, $value, $filterModel) {
                  if (trim($value) !== '') {
                      $query->andWhere('epc = :epc', [':epc' => $value]);
                  }
                  return $query;
              },
              'rule' => ['integer'],
          ],
          [
              'attribute' => 'salary',
              'label' => 'Salary',
              'rule' => ['integer'],
          ],
          [
              'attribute' => 'epc',
              'rule' => ['integer'],
          ],
          [
              'name' => 'actions',
              'class' => \yii\grid\ActionColumn::className(),
              'header' => 'Actions',
              'freeze' => true,
          ]
      ],
     ]);
     // You can change the configuration after initialization component.
     $configurator->addColumn([
          'attribute' => 'id',
          'name' => 'id_1',
          'freeze' => false,
     ]);
    
     return $this->render('grid', [
          'configurator' => $configurator,
     ]);
     
 ```
 
View grid.php:
 ```     
   GridView::widget($configurator->getGridViewConfig([
       'filterPosition' => GridView::FILTER_POS_OFF,
       'dataProvider' => new ActiveDataProvider($configurator->getActiveDataProviderConfig([
           'pagination' => [
               'pageSize' => 5,
           ],
       ])),
  ]));
```

Extended filters:
```
    $filterModel = $configurator->getFilterModel();
    $form->field($filterModel, 'epc')->textInput();
    $form->field($filterModel, 'salary')->range([
       'clientOptions' => [
           'min' => 0,
           'max' => 5000,
           'from' => 1000,
           'to' => 4000,
           'type' => 'double',
           'step' => 1,
           'prefix' => "$",
           'prettify' => false,
           'hasGrid' => true
       ],
    ]);

```