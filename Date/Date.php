<?php

declare(strict_types=1);

namespace System\Date;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use System\Exception\SystemException;
use System\Language\Language;

class Date {
   const ATOM    = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T20:55:30+02:00
   const COOKIE  = "EEEE, dd-MMM-yy HH:mm:ss z";   // Friday, 21-Feb-14 20:56:21 EET
   const ISO8601 = "yyyy-MM-dd'T'HH:mm:ssO";       // 2014-02-21T20:57:15+0200
   const RFC822  = "EEE, dd MMM yy HH:mm:ss O";    // Fri, 21 Feb 2014 20:58:24 +0200
   const RFC850  = "EEEE, dd-MMM-yy HH:mm:ss z";   // Friday, 21-Feb-14 20:59:23 EET
   const RFC1036 = "EEE, dd MMM yy HH:mm:ss O";    // Fri, 21 Feb 14 21:00:17 +0200
   const RFC1123 = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:00:58 +0200
   const RFC2822 = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:01:35 +0200
   const RFC3339 = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T21:02:31+02:00
   const RSS     = "EEE, dd MMM yyyy HH:mm:ss O";  // Fri, 21 Feb 2014 21:03:26 +0200
   const W3C     = "yyyy-MM-dd'T'HH:mm:ssXXX";     // 2014-02-21T21:04:09+02:00
   const GENERIC = "yyyy-MM-dd HH:mm:ss";          // 2014-02-21 21:04:55

   private $dateFormat;
   private $timezone;
   private $locale;
   private $date_type;
   private $time_type;
   private $calendar;
   private $timestamp;
   private $pattern;
   private $formatter;
   private $datetime;

   public function __construct(
      private Language $language
   ) {
      $config = import_config('defines.language.locales')[$this->language->getLocale()];

      $this->dateFormat = $config['date_format'];
      $this->timezone = $config['timezone'];
      $this->locale = $config['locale'];
      $this->date_type = $config['date_type'];
      $this->time_type = $config['time_type'];
      $this->calendar = $config['calendar'];

      date_default_timezone_set($this->timezone);
      setlocale(LC_TIME, $this->locale);
      $this->formatter = new IntlDateFormatter($this->locale, $this->date_type, $this->time_type, $this->timezone, $this->calendar);
      $this->pattern = $this->formatter->getPattern();
      $this->datetime = new DateTime('now', new DateTimeZone($this->timezone));
      $this->timestamp = $this->datetime->getTimestamp();
   }

   public function getDate(string|null|true $pattern = true): string {
      if (is_string($pattern)) {
         $this->formatter->setPattern($pattern);
      } else if ($pattern) {
         $this->formatter->setPattern($this->pattern);
      } else {
         $this->formatter->setPattern($this->pattern);
      }
      $date = $this->datetime->setTimestamp($this->timestamp);
      return $this->formatter->format($date);
   }

   public function getTimestamp(): int {
      return $this->timestamp;
   }

   public function getYear(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('Y');
   }

   public function getMonth(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('m');
   }

   public function getMonthString(bool $short = false): string {
      $this->formatter->setPattern($short ? 'MMM' : 'MMMM');
      return $this->formatter->format($this->timestamp);
   }

   public function getDay(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('d');
   }

   public function getDayString(bool $short = false): string {
      $this->formatter->setPattern($short ? 'EEE' : 'EEEE');
      return $this->formatter->format($this->timestamp);
   }

   public function getHour(bool $mode = true): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format($mode ? 'H' : 'h');
   }

   public function getMinute(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('m');
   }

   public function getSecond(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('s');
   }

   public function getMiliSecond(): string {
      $this->datetime->setTimestamp($this->timestamp);
      $date = new DateTimeImmutable();
      $date->setTimestamp($this->timestamp);
      return $date->format('u');
   }

   public function getDayOfWeek(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('w');
   }

   public function getDayOfYear(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('z');
   }

   public function getWeekOfYear(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('W');
   }

   public function getDaysInMonth(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('t');
   }

   public function isLeapYear(): string {
      $this->datetime->setTimestamp($this->timestamp);
      return $this->datetime->format('L');
   }

   public function now(): self {
      $this->timestamp = time();
      return $this;
   }

   public function setDate(mixed $date, $format = null): self {
      if (is_null($format)) {
         $date = date_create($date);
         $this->datetime->setTimestamp($date->getTimestamp());
         $this->timestamp = $date->getTimestamp();
      } else {
         $parse = date_parse_from_format($format, $date);
         $date = date_create_from_format($format, $date);
         if ($date) {
            if (!$parse['hour'] && !$parse['minute'] && !$parse['second'] && !$parse['fraction']) {
               $date->setTime(0, 0, 0, 0);
            }
            $this->datetime->setTimestamp($date->getTimestamp());
            $this->timestamp = $date->getTimestamp();
         } else {
            throw new SystemException('Invalid date format');
         }
      }

      return $this;
   }

   public function setTimestamp(int $timestamp): self {
      $this->timestamp = $timestamp;
      return $this;
   }

   public function setTimezone(string $timezone): self {
      $this->datetime->setTimezone(new DateTimeZone($timezone));
      $this->formatter->setTimeZone(new DateTimeZone($timezone));
      return $this;
   }

   public function setLocale(string $locale): self {
      $this->formatter = new IntlDateFormatter($locale, $this->date_type, $this->time_type, $this->timezone, $this->calendar);
      $this->pattern = $this->formatter->getPattern();
      return $this;
   }

   public function setYear(int $year): self {
      $this->datetime->setDate($year, (int) $this->getMonth(), (int) $this->getDay());
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function setMonth(int $month): self {
      $this->datetime->setDate((int) $this->getYear(), $month, (int) $this->getDay());
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function setDay(int $day): self {
      $this->datetime->setDate((int) $this->getYear(), (int) $this->getMonth(), $day);
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function setHour(int $hour): self {
      $this->datetime->setTime($hour, (int) $this->getMinute(), (int) $this->getSecond());
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function setMinute(int $minute): self {
      $this->datetime->setTime((int) $this->getHour(), $minute, (int) $this->getSecond());
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function setSecond(int $second): self {
      $this->datetime->setTime((int) $this->getHour(), (int) $this->getMinute(), $second);
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addYear(int $year): self {
      $this->datetime->modify('+' . $year . ' year');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addMonth(int $month): self {
      $this->datetime->modify('+' . $month . ' month');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addDay(int $day): self {
      $this->datetime->modify('+' . $day . ' day');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addHour(int $hour): self {
      $this->datetime->modify('+' . $hour . ' hour');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addMinute(int $minute): self {
      $this->datetime->modify('+' . $minute . ' minute');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function addSecond(int $second): self {
      $this->datetime->modify('+' . $second . ' second');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractYear(int $year): self {
      $this->datetime->modify('-' . $year . ' year');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractMonth(int $month): self {
      $this->datetime->modify('-' . $month . ' month');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractDay(int $day): self {
      $this->datetime->modify('-' . $day . ' day');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractHour(int $hour): self {
      $this->datetime->modify('-' . $hour . ' hour');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractMinute(int $minute): self {
      $this->datetime->modify('-' . $minute . ' minute');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function subtractSecond(int $second): self {
      $this->datetime->modify('-' . $second . ' second');
      $this->timestamp = $this->datetime->getTimestamp();
      return $this;
   }

   public function compareDates(array $date = [], ?string $format = null): array {
      if (!isset($date[0]) || isset($date[2])) {
         throw new SystemException('Invalid date format');
      }

      if (is_null($format)) {
         if (isset($date[1])) {
            $datetime1 = date_create($date[0]);
            $datetime2 = date_create($date[1]);
         } else {
            $datetime1 = $this->datetime;
            $datetime2 = date_create($date[0]);
         }
      } else {
         if (isset($date[1])) {
            $datetime1 = date_create_from_format($format, $date[0]);
            $datetime2 = date_create_from_format($format, $date[1]);
         } else {
            $datetime1 = date_create_from_format($format, $this->datetime->format($format));
            $datetime2 = date_create_from_format($format, $date[0]);
         }
      }

      $interval = $datetime1->diff($datetime2);
      $isEqual = ($interval->y === 0 && $interval->m === 0 && $interval->d === 0 && $interval->h === 0 && $interval->i === 0 && $interval->s === 0) ? 1 : 0;
      $isBefore = ($datetime1 < $datetime2) ? 1 : 0;
      $isAfter = ($datetime1 > $datetime2) ? 1 : 0;
      $diffInYears = $interval->y;
      $diffInMonths = $interval->y * 12 + $interval->m;
      $diffInDays = $interval->days;
      $diffInHours =  $interval->h + ($interval->days * 24);
      $diffInMinutes = $interval->i + ($diffInHours * 60);
      $diffInSeconds = $interval->i + ($diffInMinutes * 60);

      return [
         'years' => $interval->y,
         'months' => $interval->m,
         'days' => $interval->d,
         'hours' => $interval->h,
         'minutes' => $interval->i,
         'seconds' => $interval->s,
         'isEqual' => $isEqual,
         'isBefore' => $isBefore,
         'isAfter' => $isAfter,
         'diff' => [
            'inYears' => $diffInYears,
            'inMonths' => $diffInMonths,
            'inDays' => $diffInDays,
            'inHours' => $diffInHours,
            'inMinutes' => $diffInMinutes,
            'inSeconds' => $diffInSeconds
         ]
      ];
   }

   public function getHumanTime(?string $time = null): mixed {
      if (is_null($time)) {
         $time = $this->timestamp;
      } else {
         $time = strtotime($time);
      }

      $time_diff = time() - $time;
      $second = $time_diff;
      $minute = round($time_diff / 60);
      $hour = round($time_diff / 3600);
      $day = round($time_diff / 86400);
      $week = round($time_diff / 604800);
      $month = round($time_diff / 2419200);
      $year = round($time_diff / 29030400);

      if ($second < 60) {
         if ($second === 0) {
            return $this->language->system('date.just');
         } else {
            return $this->language->system('date.seconds_ago', [$second]);
         }
      } else if ($minute < 60) {
         return $this->language->system('date.minutes_ago', [$minute]);
      } else if ($hour < 24) {
         return $this->language->system('date.hours_ago', [$hour]);
      } else if ($day < 7) {
         return $this->language->system('date.days_ago', [$day]);
      } else if ($week < 4) {
         return $this->language->system('date.weeks_ago', [$week]);
      } else if ($month < 12) {
         return $this->language->system('date.months_ago', [$month]);
      } else {
         return $this->language->system('date.years_ago', [$year]);
      }
   }
}
