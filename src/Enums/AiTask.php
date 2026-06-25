<?php

declare(strict_types=1);

namespace Eloquage\LaravelAiModelTiers\Enums;

enum AiTask: string
{
    case Translation = 'translation';
    case OcrVision = 'ocr_vision';
    case Embedding = 'embedding';
    case Summarization = 'summarization';
    case Correlation = 'correlation';
    case StructuredExtraction = 'structured_extraction';
}
