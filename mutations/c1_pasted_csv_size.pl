# import_csv_form::validation(): pasted CSV of any size is accepted.
s~strlen\(\(string\)\(\$data\['csvtext'\] \?\? ''\)\) > manager::MAX_CSV_BYTES~false~s;
