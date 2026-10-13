# list_domains(): inactive items are counted.
s~SUM\(CASE WHEN i\.active = 1 THEN 1 ELSE 0 END\)~SUM(1)~s;
