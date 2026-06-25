<?php
declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

final class ScraperLogger
{
  public static function log(string $level, string $message): void
  {
    ScraperPaths::ensureDirs();
    $line = sprintf(
      "[%s] [%s] %s\n",
      date('Y-m-d H:i:s'),
      strtoupper($level),
      $message
    );
    file_put_contents(ScraperPaths::logFile(), $line, FILE_APPEND | LOCK_EX);
  }

  public static function tail(int $maxLines = 120): string
  {
    $path = ScraperPaths::logFile();
    if (!is_file($path)) {
      return '';
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if (count($lines) <= $maxLines) {
      return implode("\n", $lines);
    }

    return implode("\n", array_slice($lines, -$maxLines));
  }
}
