<?php

namespace DeepseekPhp\Enums\Configs;

enum DefaultConfigs: string
{
    case BASE_URL = 'https://api.deepseek.com/v3';
    case MODEL = 'deepseek-chat';
    case TIMEOUT = '30';
    case STREAM = 'false';
}
