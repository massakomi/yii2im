<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%files}}`.
 */
class m231107_134203_create_files_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%files}}', [
            'id' => $this->primaryKey(),
            'path' => $this->string()->notNull(),
            'date_added' => $this->datetime(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%files}}');
    }
}
