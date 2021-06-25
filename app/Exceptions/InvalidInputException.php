<?php
namespace App\Exceptions;

use Exception;
use JetBrains\PhpStorm\Pure;

class InvalidInputException extends Exception {
    private array $inputs;

    public function __construct($message = "", $data = [], $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getInputs(): array {
        return $this->inputs;
    }

    /**
     * @return array inputs
     */
    #[Pure] public function context(): array {
        return $this->getInputs();
    }

    public function report(): bool {
        return true;
    }

    public function response() {
        $msg = $this->getMessage();

        response()->json([
            "message" => empty($msg) ? "請求資料不正確。" : $msg,
            "extra" => $this->getInputs(),
        ], 400);
    }
}
