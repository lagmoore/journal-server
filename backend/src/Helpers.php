<?php
// backend/src/Helpers.php
namespace Vyper;

use DateTime;
use DateTimeZone;

class Helpers
{
    /**
     * Get current datetime as string in Y-m-d H:i:s format
     * 
     * @return string Current datetime
     */
    public static function now(): string
    {
        // Get current time in configured timezone
        $timezone = $_ENV['APP_TIMEZONE'] ?? 'UTC';
        $dateTime = new DateTime('now', new DateTimeZone($timezone));
        
        return $dateTime->format('Y-m-d H:i:s');
    }
    
    /**
     * Add seconds to a datetime
     * 
     * @param string $datetime Datetime string in Y-m-d H:i:s format
     * @param int $seconds Seconds to add
     * @return string New datetime
     */
    public static function addSeconds(string $datetime, int $seconds): string
    {
        $date = new DateTime($datetime);
        $date->modify("+{$seconds} seconds");
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Check if a datetime is in the past
     * 
     * @param string $datetime Datetime string to check
     * @return bool True if datetime is in the past
     */
    public static function isPast(string $datetime): bool
    {
        $now = new DateTime();
        $date = new DateTime($datetime);
        
        return $now > $date;
    }
    
    /**
     * Convert a timestamp to MySQL datetime format
     * 
     * @param int $timestamp Unix timestamp
     * @return string Formatted datetime
     */
    public static function timestampToDateTime(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}