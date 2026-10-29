<?php
declare(strict_types=1);
final class MarketplaceOAuthOrigin
{
    public static function matches(string $callback,string $origin): bool
    {
        $normalize=static function(string $url):?array {
            $parts=parse_url($url);
            if(!$parts||empty($parts['host'])||!in_array(strtolower($parts['scheme']??''),['https','http'],true))return null;
            $scheme=strtolower($parts['scheme']);
            return [$scheme,strtolower($parts['host']),(int)($parts['port']??($scheme==='https'?443:80))];
        };
        $expected=$normalize($callback);$actual=$normalize($origin);
        return $expected!==null&&$actual!==null&&$expected===$actual;
    }
}
