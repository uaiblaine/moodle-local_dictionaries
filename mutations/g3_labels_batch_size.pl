# get_reference_labels: the batch size check never fires.
s~count\(\$params\['codes'\]\) > manager::MAX_BATCH_SIZE~count(\$params['codes']) > manager::MAX_BATCH_SIZE * 1000~s;
