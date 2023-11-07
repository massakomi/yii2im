<?php

namespace frontend\models;

use yii\db\ActiveRecord;

class Products extends ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'price', 'image'], 'required'],
            [['image',], 'integer'],
            [['price'], 'double'],
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
            'name' => 'Название',
            'price' => 'Цена',
            'date_added' => 'Дата',
            'image' => 'Изображение',
        ];
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%products}}';
    }


    public function getImage()
    {
        return $this->hasOne(Files::class, ['id' => 'image']);
    }

}