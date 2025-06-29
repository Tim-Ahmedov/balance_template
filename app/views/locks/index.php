<?php

use app\models\Locks;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Locks';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="locks-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            [
                'attribute' => 'user_id',
                'content' => static function (Locks $model) {
                    return $model->user->getFullName();
                },
            ],
            'amount',
            [
                'attribute' => 'status',
                'contentOptions' => static function (Locks $model,) {
                    return match ($model->status) {
                        'locked' => ['class' => 'text-primary'],
                        'refunded' => ['class' => 'text-success'],
                        default => ['class' => 'text-danger'],
                    };
                },
            ],
            'transaction_id',
            'comment:ntext',
            'status',
            'created_at',
            'updated_at',
            [
                'content' => static function (Locks $model) {
                    return  $model->status === 'locked' ? '<a href="/locks/refund?id=' . $model->id . '" class="btn btn-success">Вернуть</a>' : '';
                },
            ],
            [
                'content' => static function (Locks $model) {
                    return  $model->status === 'locked' ? '<a href="/locks/complete?id=' . $model->id . '" class="btn btn-danger">Списать</a>' : '';
                },
            ],
        ],
    ]); ?>


</div>
