# is_open(): every existing domain counts as open, whatever its recorded visibility.
s~return \$record && \(int\)\$record->visibility === self::VISIBILITY_LOGGEDIN;~return (bool)\$record;~s;
