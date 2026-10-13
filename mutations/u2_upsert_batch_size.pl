# upsert_reference_items: the batch size check never fires.
s~count\(\$params\['items'\]\) > manager::MAX_BATCH_SIZE~count(\$params['items']) > manager::MAX_BATCH_SIZE * 1000~s;
