<?php

use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Балансы пользователей';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="worker-test-balances">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            'balance',
            'created_at',
            'updated_at',
        ],
    ]) ?>

    <p class="mt-4">
        <?= Html::a('Назад к форме', ['index'], ['class' => 'btn btn-secondary']) ?>
    </p>
</div> 