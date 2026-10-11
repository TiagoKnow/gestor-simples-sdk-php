<?php

namespace GestorSimples\Exceptions;

/**
 * Webhook que não veio do GestorSimples (ou foi alterado no caminho):
 * responda 401 e não processe.
 */
class InvalidSignatureException extends \UnexpectedValueException {}
