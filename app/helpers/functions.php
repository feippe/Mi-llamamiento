<?php

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function iso_to_mysql(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
}

function quarter_label(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    $month = (int) gmdate('n', $ts);
    $quarter = (int) ceil($month / 3);
    return gmdate('Y', $ts) . '-Q' . $quarter;
}
