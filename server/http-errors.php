<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    http_response_code(403);header('Cache-Control: no-store');exit;
}

// Typed denials preserve DomainException compatibility for existing callers.
final class BoisAuthenticationException extends DomainException {}
final class BoisForbiddenException extends DomainException {}

function bois_http_domain_status(DomainException $error): int
{
    if ($error instanceof BoisAuthenticationException) return 401;
    if ($error instanceof BoisForbiddenException) return 403;
    return 409;
}
