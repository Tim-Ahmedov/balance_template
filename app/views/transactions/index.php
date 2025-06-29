<?php

use app\models\Transactions;
use yii\helpers\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Transactions';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="users-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create transaction', ['create'], ['class' => 'btn btn-success']) ?>
    </p>


    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            [
                'attribute' => 'user_id',
                'content' => static function (Transactions $model, $key, $index, $column) {
                    return $model->user->getFullName();
                },
            ],
            'amount',
            'type',
            [
                'attribute' => 'from_id',
                'content' => static function (Transactions $model, $key, $index, $column) {
                    return $model->from_id !== 0 ? $model->userFrom->getFullName() : '';
                },
            ],
            [
                'attribute' => 'status',
                'contentOptions' => static function (Transactions $model, $key, $index, $column) {
                    return match ($model->status) {
                        'new' => ['class' => 'text-primary'],
                        'success' => ['class' => 'text-success'],
                        default => ['class' => 'text-danger'],
                    };
                },
            ],
            'status',
            'comment',
        ],
    ]) ?>
</div>
