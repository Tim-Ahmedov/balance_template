<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model yii\base\DynamicModel */
/* @var $result string|null */

$this->title = 'Тестирование воркера: отправка операций';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="worker-test-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($result): ?>
        <div class="alert alert-success"><?= Html::encode($result) ?></div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'operation')->dropDownList([
        'credit' => 'Credit',
        'debit' => 'Debit',
        'lock' => 'Lock',
        'unlock' => 'Unlock',
        'transfer' => 'Transfer',
    ], ['prompt' => 'Выберите операцию']) ?>

    <?= $form->field($model, 'user_id')->textInput() ?>
    <?= $form->field($model, 'amount')->textInput() ?>
    <?= $form->field($model, 'operation_id')->textInput(['value' => rand(1000,9999)]) ?>
    <?= $form->field($model, 'to_user_id')->textInput() ?>
    <?= $form->field($model, 'lock_id')->textInput() ?>
    <?= $form->field($model, 'confirm')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Отправить', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    <p class="mt-4">
        <?= Html::a('Посмотреть балансы пользователей', ['balances'], ['class' => 'btn btn-info']) ?>
    </p>
</div> 