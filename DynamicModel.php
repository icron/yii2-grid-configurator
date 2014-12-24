<?php

namespace icron\configurator;

class DynamicModel extends \yii\base\DynamicModel
{
    public $formName;

    public $labels = [];

    public function attributeLabels()
    {
        return $this->labels;
    }

    public function formName()
    {
        if ($this->formName) {
            return $this->formName;
        } else {
            return parent::formName();
        }
    }
}