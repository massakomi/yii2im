<?php

namespace frontend\models;

use yii\db\ActiveRecord;

class Files extends ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['path'], 'required'],
            [['date_added'], 'date', 'format' => 'php:Y-m-d H:i:s']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'path' => 'Путь',
            'date_added' => 'Дата',
        ];
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%files}}';
    }

    public function getProduct()
    {
        return $this->hasOne(Products::class, ['id' => 'image']);
    }
}