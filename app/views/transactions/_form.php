<?php

use app\services\operations\OperationType;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Transactions $model */
/** @var app\models\Users[] $users */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="transactions-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'user_id')->dropDownList($users) ?>

    <?= $form->field($model, 'amount')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'type')->dropDownList(array_combine(OperationType::getTransactionTypes(), OperationType::getTransactionTypes())) ?>

    <?= $form->field($model, 'from_id')->dropDownList(array_merge([0 => ''], $users))
        ->hint('Если это перевод от другого пользователя, укажите его, иначе оставьте пустым') ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
