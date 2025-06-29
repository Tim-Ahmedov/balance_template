<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Transactions $model */
/** @var app\models\Users[] $users */

$this->title = 'Create Transactions';
$this->params['breadcrumbs'][] = ['label' => 'Transactions', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="transactions-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'users' => $users,
    ]) ?>

</div>
