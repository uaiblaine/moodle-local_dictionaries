# The write transaction stops bumping the domain revision.
s~(private function write_batch\(.*?)SET revision = revision \+ 1, timemodified = :now~$1SET revision = revision, timemodified = :now~s;
