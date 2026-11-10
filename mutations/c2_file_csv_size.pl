# import_csv_form::validation(): an uploaded CSV of any size is accepted.
s~\$file->get_filesize\(\) > manager::MAX_CSV_BYTES~false~s;
