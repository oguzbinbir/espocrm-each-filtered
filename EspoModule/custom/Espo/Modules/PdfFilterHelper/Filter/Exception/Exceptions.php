<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter\Exception;

class TokenizerException extends \RuntimeException {}
class ParseException    extends \RuntimeException {}
class CompilerException extends \RuntimeException {}

class FilterParseException extends \RuntimeException {}
