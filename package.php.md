# V8 Fix
Fixed HTTP 500 on frontend pages caused by calling db() before loading config/database.php.
No database import required. Replace the existing website folder with this package and hard refresh.
