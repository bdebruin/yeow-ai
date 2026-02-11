<?php
file_put_contents(
  __DIR__ . '/cron_test.log',
  date('c') . " cron ran\n",
  FILE_APPEND | LOCK_EX
);
