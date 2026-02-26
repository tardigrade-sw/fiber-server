<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore;

class HttpStatus {
    public const int OK = 200;
    public const int NotModified = 304;
    public const int MovedPermanantly = 301;
    public const int Found = 302;
    public const int SeeOther = 303;
    public const int PermanentRedirect = 308;
    public const int BadRequest = 400;
    public const int Forbidden = 403;
    public const int NotFound = 404;
    public const int InternalServerError = 500;


    public static function getMessage(int $status): string {
        return match($status) {
            self::OK => 'OK',
            self::NotModified => 'Not Modified',
            self::Found => 'Found',
            self::MovedPermanantly => 'Moved Permanently',
            self::PermanentRedirect => 'Permanent Redirect',
            self::BadRequest => 'Bad Request',
            self::Forbidden => 'Forbidden',
            self::NotFound => 'Not Found',
            self::InternalServerError => 'Internal Server Error',
            default => 'Not Implemented'
        };
    }
}