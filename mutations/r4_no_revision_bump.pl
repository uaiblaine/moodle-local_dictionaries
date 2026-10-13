# The write transaction stops bumping the domain revision.
s~SET revision = revision \+ 1, timemodified = :now~SET revision = revision, timemodified = :now~s;
