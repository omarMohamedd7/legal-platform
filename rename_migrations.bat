@echo off
cd database\migrations

:: Rename migration files
ren "2025_06_20_000000_create_judge_tasks_table.php" "create_judge_tasks_table.php"
ren "2025_06_19_000000_create_payments_table.php" "create_payments_table.php"
ren "2025_06_18_000000_create_consultation_requests_table.php" "create_consultation_requests_table.php"
ren "2025_06_17_000000_update_consult_fee_in_lawyers_table.php" "update_consult_fee_in_lawyers_table.php"
ren "2024_06_16_000000_create_case_attachments_table.php" "create_case_attachments_table.php"
ren "2025_05_20_000000_create_video_analyses_table.php" "create_video_analyses_table.php"
ren "2025_05_15_000000_create_legal_books_table.php" "create_legal_books_table.php"
ren "2025_05_10_000000_create_court_sessions_table.php" "create_court_sessions_table.php"
ren "2025_05_01_122554_normalize_published_cases_table.php" "normalize_published_cases_table.php"
ren "2025_04_29_113549_create_published_cases_table.php" "create_published_cases_table.php"
ren "2025_04_24_230859_create_cases_table.php" "create_cases_table.php"
ren "2025_04_28_214705_create_case_requests_table.php" "create_case_requests_table.php"
ren "2025_04_28_205553_update_case_type_in_cases_table.php" "update_case_type_in_cases_table.php"
ren "2025_04_28_205149_update_case_types_enum.php" "update_case_types_enum.php"
ren "2025_04_26_162802_add_consult_fee_to_lawyers_table.php" "add_consult_fee_to_lawyers_table.php"
ren "2025_04_23_154800_create_clients_table.php" "create_clients_table.php"
ren "2025_04_24_153725_create_judges_table.php" "create_judges_table.php"
ren "2025_04_23_155409_create_lawyers_table.php" "create_lawyers_table.php"
ren "0001_01_01_000000_create_users_table.php" "create_users_table.php"
ren "2025_04_21_125305_create_personal_access_tokens_table.php" "create_personal_access_tokens_table.php"
ren "0001_01_01_000001_create_cache_table.php" "create_cache_table.php"
ren "0001_01_01_000002_create_jobs_table.php" "create_jobs_table.php"

echo Migration files renamed successfully.
echo List of renamed files:
dir 