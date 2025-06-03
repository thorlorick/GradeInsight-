<?php
// config.php

$configVariables = include('config_variables.php');

return [
    'apiKey' => $configVariables['apiKey'] ?? null,
    'spreadsheetIds' => $configVariables['spreadsheetIds'] ?? [],
];
