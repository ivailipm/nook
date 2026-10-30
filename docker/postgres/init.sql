-- Runs once, when the volume is first created. `nook` (the dev database) comes from POSTGRES_DB.
-- nook_testing: the Pest suite. nook_factory: dev servers in factory worktrees.
CREATE DATABASE nook_testing OWNER nook;
CREATE DATABASE nook_factory OWNER nook;
