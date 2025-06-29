<?php
namespace app\services;

enum OperationType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
    case TRANSFER = 'transfer';
    case LOCK = 'lock';
    case UNLOCK = 'unlock';
} 