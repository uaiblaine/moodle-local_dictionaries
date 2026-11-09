# manage.php: the import runs under the request's normal time and memory limits.
s~    core_php_time_limit::raise\(HOURSECS\);\n    raise_memory_limit\(MEMORY_EXTRA\);\n~~s;
