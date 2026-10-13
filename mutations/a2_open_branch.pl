# require_read(): the is_open() branch is never taken, so an open domain needs the capability too.
s~(public static function require_read\(string \$domain\): void \{\n\s+)if \(self::is_open\(\$domain\)\) \{~$1if (false && self::is_open(\$domain)) {~s;
