#!/usr/bin/env bash
# ============================================
# Deploy & FTP script for Pluribus (chante.vzs.mx / chante-api.vzs.mx)
# ============================================
# Deploy: build frontend (optional) and upload backend and/or frontend.
# FTP: list remote dirs, read remote files, or upload any file/folder.
# Credentials read from .secrets (key=value: ftp_user=, ftp_password=; or legacy FTP: section).
#
# Deploy Usage:
#   ./deploy.sh [all]       Build frontend, upload frontend + backend
#   ./deploy.sh backend     Upload backend only
#   ./deploy.sh env         Upload .env.production as backend/.env
#   ./deploy.sh file <local> [remote]  Upload a single file
#
# FTP Usage (like generic ftp tool):
#   ./deploy.sh list <remote_path>           List remote directory
#   ./deploy.sh cat <remote_path>            Read remote file
#   ./deploy.sh put <source> <destination>   Upload file or folder
#
# Examples:
#   ./deploy.sh list prod/backend
#   ./deploy.sh cat prod/backend/.env
#   ./deploy.sh put backend/.env.production prod/backend/.env
#   ./deploy.sh put frontend/dist/ prod/frontend/
# ============================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
SECRETS_FILE="$PROJECT_ROOT/.secrets"
DEBUG_LOG="${PROJECT_ROOT}/.cursor/debug.log"

# #region agent log
_debug_log() {
  local location="$1" message="$2" data="$3" hypothesis_id="${4:-}"
  local ts; ts=$(date +%s)000
  mkdir -p "$(dirname "$DEBUG_LOG")"
  local payload="{\"location\":\"$location\",\"message\":\"$message\",\"data\":$data,\"timestamp\":$ts,\"sessionId\":\"debug-session\",\"hypothesisId\":\"$hypothesis_id\"}"
  echo "$payload" >> "$DEBUG_LOG" 2>/dev/null || true
}
# #endregion

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_info() { echo -e "${BLUE}ℹ${NC} $1"; }
print_success() { echo -e "${GREEN}✓${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
print_error() { echo -e "${RED}✗${NC} $1"; }

# --- Config (override with environment variables) ---
# FTP host for chante.vzs.mx / chante-api.vzs.mx
FTP_HOST="${FTP_HOST:-ftp.virozstudio.com}"
REMOTE_BACKEND_PATH="${REMOTE_BACKEND_PATH:-prod/backend}"
REMOTE_FRONTEND_PATH="${REMOTE_FRONTEND_PATH:-prod/frontend}"
# Max seconds to allow long uploads (override via env)
FTP_TIMEOUT="${FTP_TIMEOUT:-3600}"
# Set FTP_SSL=1 for FTPS (FTP over SSL/TLS)
FTP_SSL="${FTP_SSL:-0}"

# --- Parse .secrets key=value lines (e.g. ftp_user=, db=, mysql_user=, mysql_password=) ---
read_secrets_keyval() {
  SECRETS_ftp_user=""
  SECRETS_ftp_password=""
  SECRETS_db=""
  SECRETS_mysql_user=""
  SECRETS_mysql_password=""
  [[ ! -f "$SECRETS_FILE" ]] && return
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%%#*}"
    line="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    [[ -z "$line" ]] && continue
    if [[ "$line" == *=* ]]; then
      key="${line%%=*}"; key="${key//[^a-zA-Z0-9_]/}"
      val="${line#*=}"
      val="$(echo "$val" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
      case "$key" in
        ftp_user) SECRETS_ftp_user="$val" ;;
        ftp_password) SECRETS_ftp_password="$val" ;;
        db) SECRETS_db="$val" ;;
        mysql_user) SECRETS_mysql_user="$val" ;;
        mysql_password) SECRETS_mysql_password="$val" ;;
      esac
    fi
  done < "$SECRETS_FILE"
}

# --- Read FTP credentials from .secrets (key=value or legacy FTP: section) ---
read_ftp_credentials() {
  if [[ ! -f "$SECRETS_FILE" ]]; then
    print_error ".secrets not found at $SECRETS_FILE"
    exit 1
  fi
  read_secrets_keyval
  if [[ -n "${SECRETS_ftp_user:-}" && -n "${SECRETS_ftp_password:-}" ]]; then
    FTP_USER="$SECRETS_ftp_user"
    FTP_PASS="$SECRETS_ftp_password"
    return
  fi
  local in_ftp=0 prev=""
  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" == "FTP:" ]]; then in_ftp=1; prev=""; continue; fi
    if (( in_ftp )); then
      if [[ "$prev" == "user" ]]; then FTP_USER="$line"
      elif [[ "$prev" == "password" ]]; then FTP_PASS="$line"; break; fi
      prev="$line"
    fi
  done < "$SECRETS_FILE"
  if [[ -z "${FTP_USER:-}" || -z "${FTP_PASS:-}" ]]; then
    print_error "Could not parse FTP from .secrets (use ftp_user= and ftp_password= or legacy FTP: section)"
    exit 1
  fi
}

# --- Run command with max wall-clock seconds (GNU timeout, gtimeout, or macOS fallback) ---
# On timeout: exit 124 (same as GNU timeout) so callers can detect it.
run_with_timeout() {
  local max_seconds="$1"
  shift
  if command -v timeout &>/dev/null; then
    timeout "$max_seconds" "$@"
    return $?
  fi
  if command -v gtimeout &>/dev/null; then
    gtimeout "$max_seconds" "$@"
    return $?
  fi
  "$@" &
  local pid=$!
  local elapsed=0
  while kill -0 "$pid" 2>/dev/null; do
    if (( elapsed >= max_seconds )); then
      kill -TERM "$pid" 2>/dev/null || true
      sleep 1
      kill -KILL "$pid" 2>/dev/null || true
      wait "$pid" 2>/dev/null || true
      return 124
    fi
    sleep 1
    elapsed=$((elapsed + 1))
  done
  wait "$pid"
  return $?
}

# --- Check lftp is available ---
check_lftp() {
  if ! command -v lftp &>/dev/null; then
    print_error "lftp is required. Install: macOS: brew install lftp  |  Debian/Ubuntu: sudo apt install lftp"
    exit 1
  fi
}

# --- Build lftp script preamble (ssl + open) ---
# Writes to stdout so caller can do: lftp_preamble > "$script"
lftp_preamble() {
  if [[ "$FTP_SSL" == "1" ]]; then
    cat <<EOF
set ftp:ssl-allow yes
set ftp:ssl-force no
set ftp:ssl-protect-data yes
set ftp:ssl-protect-list no
set ftp:passive-mode yes
set ssl:verify-certificate no
set net:timeout 20
set net:max-retries 2
EOF
  else
    cat <<EOF
set ftp:ssl-allow no
set net:timeout 30
EOF
  fi
  echo "open -u \"$FTP_USER\",\"$FTP_PASS\" $FTP_HOST"
}

# --- List remote directory ---
ftp_list() {
  local remote_path="$1"
  [[ -z "$FTP_HOST" ]] && { print_error "FTP_HOST is not set."; exit 1; }
  local script
  script="$(mktemp)"
  _CLEANUP_SCRIPT="$script"
  trap 'rm -f "$_CLEANUP_SCRIPT"' RETURN
  lftp_preamble > "$script"
  cat >> "$script" <<EOF

cd "$remote_path"
ls -lah
bye
EOF
  print_info "Listing $remote_path ..."
  echo ""
  if run_with_timeout 60 lftp -f "$script" 2>&1; then
    echo ""
    print_success "Listing completed"
  else
    print_error "Failed to list $remote_path"
    exit 1
  fi
}

# --- Read remote file ---
ftp_cat() {
  local remote_path="$1"
  [[ -z "$FTP_HOST" ]] && { print_error "FTP_HOST is not set."; exit 1; }
  local script
  local tmpfile
  local remote_dir remote_name
  remote_name="${remote_path##*/}"
  remote_dir="${remote_path%/*}"
  if [[ "$remote_dir" == "$remote_path" ]]; then
    remote_dir="."
  fi
  script="$(mktemp)"
  tmpfile="$(mktemp)"
  _CLEANUP_SCRIPT="$script"
  _CLEANUP_TMPFILE="$tmpfile"
  trap 'rm -f "$_CLEANUP_SCRIPT" "$_CLEANUP_TMPFILE"' RETURN
  lftp_preamble > "$script"
  # mktemp creates an empty file; lftp get refuses to overwrite unless removed
  rm -f "$tmpfile"
  # cd into parent dir then get basename — some hosts reject full-path RETR
  cat >> "$script" <<EOF

cd "$remote_dir"
get "$remote_name" -o "$tmpfile"
bye
EOF
  print_info "Reading $remote_path ..."
  echo ""
  local lftp_out lftp_exit
  set +e
  lftp_out="$(run_with_timeout 120 lftp -f "$script" 2>&1)"
  lftp_exit=$?
  set -e
  if [[ $lftp_exit -ne 0 ]]; then
    print_error "Failed to read $remote_path (lftp exit $lftp_exit)"
    echo "$lftp_out"
    exit 1
  fi
  if [[ -f "$tmpfile" ]]; then
    cat "$tmpfile"
    echo ""
    print_success "File read completed"
  else
    print_error "Failed to read $remote_path"
    exit 1
  fi
}

# --- Upload file or directory (generic put) ---
ftp_put_generic() {
  local source="$1"
  local destination="$2"
  [[ -z "$FTP_HOST" ]] && { print_error "FTP_HOST is not set."; exit 1; }
  [[ ! -e "$source" ]] && { print_error "Source does not exist: $source"; exit 1; }
  source="$(cd "$(dirname "$source")" && pwd)/$(basename "$source")"
  local script
  script="$(mktemp)"
  _CLEANUP_SCRIPT="$script"
  trap 'rm -f "$_CLEANUP_SCRIPT"' RETURN
  lftp_preamble > "$script"
  if [[ -d "$source" ]]; then
    local remote_dir remote_name
    destination="${destination%/}"
    remote_dir="$(dirname "$destination")"
    remote_name="$(basename "$destination")"
    [[ "$remote_dir" == "." ]] && remote_dir=""
    # Prefer uploading into existing dir (cd full path, mirror .) to avoid 550 "File exists" when mirror creates the leaf
    local check_script; check_script="$(mktemp)"
    _CLEANUP_SCRIPT="$script"
    _CLEANUP_CHECK_SCRIPT="$check_script"
    trap 'rm -f "$_CLEANUP_SCRIPT" "$_CLEANUP_CHECK_SCRIPT"' RETURN
    lftp_preamble > "$check_script"
    echo "cd $destination" >> "$check_script"
    echo "bye" >> "$check_script"
    local dest_exists=0
    if run_with_timeout 30 lftp -f "$check_script" >/dev/null 2>&1; then
      dest_exists=1
    fi
    if [[ $dest_exists -eq 1 ]]; then
      echo "cd $destination" >> "$script"
      cat >> "$script" <<EOF

mirror -R -v -e --delete "$source" .
bye
EOF
    else
      if [[ -n "$remote_dir" ]]; then
        echo "cd $remote_dir" >> "$script"
      fi
      cat >> "$script" <<EOF

mirror -R -v -e --delete "$source" "$remote_name"
bye
EOF
    fi
    print_info "Uploading directory $source -> $destination ..."
    local lftp_out lftp_exit
    set +e
    lftp_out="$(run_with_timeout "$FTP_TIMEOUT" lftp -f "$script" 2>&1)"
    lftp_exit=$?
    set -e
    echo "$lftp_out"
    # #region agent log
    has_transfer=0; echo "$lftp_out" | grep -qE "Transferring file|Making directory" && has_transfer=1
    has_550=0; echo "$lftp_out" | grep -qE "550|File exists|mkdir: Access failed" && has_550=1
    snippet="${lftp_out:0:500}"; snippet="${snippet//\\/\\\\}"; snippet="${snippet//\"/\\\"}"; snippet="${snippet//$'\n'/\\n}"
    _debug_log "deploy.sh:ftp_put_generic" "lftp result" "{\"lftp_exit\":$lftp_exit,\"dest_exists\":$dest_exists,\"has_transfer\":$has_transfer,\"has_550\":$has_550,\"snippet\":\"$snippet\"}" "post-fix"
    # #endregion
    # lftp can exit non-zero when server returns 550 "File exists" for existing dirs; treat as success if files were transferred
    if [[ $lftp_exit -eq 124 ]]; then
      print_error "Upload timed out after ${FTP_TIMEOUT}s (set FTP_TIMEOUT to increase)"
      exit 1
    elif [[ $lftp_exit -eq 0 ]]; then
      print_success "Upload completed"
    elif echo "$lftp_out" | grep -qE "Transferring file|Making directory" && echo "$lftp_out" | grep -qE "550|File exists|mkdir: Access failed"; then
      print_success "Upload completed (server reported existing directories; files were updated)"
    else
      print_error "Upload failed"
      exit 1
    fi
  else
    local remote_dir remote_file
    if [[ "$destination" == */ ]]; then
      remote_dir="${destination%/}"
      remote_file="$(basename "$source")"
    else
      remote_dir="$(dirname "$destination")"
      remote_file="$(basename "$destination")"
    fi
    [[ "$remote_dir" == "." ]] && remote_dir=""
    cat >> "$script" <<EOF

mkdir -p $remote_dir
cd ${remote_dir:-.}
put "$source" -o "$remote_file"
bye
EOF
    print_info "Uploading file $source -> $destination ..."
    if run_with_timeout 300 lftp -f "$script" 2>&1; then
      print_success "Upload completed"
    else
      print_error "Upload failed"
      exit 1
    fi
  fi
}

# --- Build frontend ---
build_frontend() {
  echo "Building frontend..."
  # Use a dedicated output dir to avoid permission issues from root-owned dist artifacts.
  (
    cd "$PROJECT_ROOT/frontend"
    local install_deps="${INSTALL_NPM_DEPS:-0}"
    # Backward compatibility: explicit skip always wins.
    if [[ "${SKIP_NPM_INSTALL:-0}" == "1" ]]; then
      install_deps=0
    fi
    if [[ "$install_deps" == "1" ]]; then
      print_info "Installing frontend dependencies (with retry/offline-friendly flags)..."
      npm install --no-audit --prefer-offline --fetch-retries=5 --fetch-retry-maxtimeout=120000 \
        || npm install --no-audit --fetch-retries=5 --fetch-retry-maxtimeout=120000
    else
      print_info "Skipping npm install by default (use --install-deps to force install)."
    fi
    npm run build -- --outDir dist-deploy
  )
  if [[ ! -d "$PROJECT_ROOT/frontend/dist-deploy" ]]; then
    echo "Error: Frontend build did not produce frontend/dist-deploy"
    exit 1
  fi
  echo "Frontend build done."
}

# --- Upload directory with lftp (reverse mirror) ---
# Usage: ftp_upload_local_to_remote "local_dir" "remote_path"
# Uploads contents of local_dir into remote_path (remote_path created if needed).
ftp_upload() {
  local local_dir="$1"
  local remote_path="$2"
  if [[ -z "$FTP_HOST" ]]; then
    echo "Error: FTP_HOST is not set. Set it in the script or: FTP_HOST=ftp.yourhost.com $0"
    exit 1
  fi
  if [[ ! -d "$local_dir" ]]; then
    echo "Error: Local directory does not exist: $local_dir"
    exit 1
  fi
  echo "Uploading $local_dir -> ftp://$FTP_HOST/$remote_path ..."
  lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
    set ftp:ssl-allow no;
    set net:timeout 30;
    mkdir -p $remote_path;
    mirror -R -v -e --delete $local_dir $remote_path;
    bye
  "
  echo "Upload done: $remote_path"
}

# --- Upload backend (excluding vendor, node_modules, .env, etc.) ---
upload_backend() {
  local backend_dir="$PROJECT_ROOT/backend"
  local remote_path="$REMOTE_BACKEND_PATH"
  if [[ -z "$FTP_HOST" ]]; then
    echo "Error: FTP_HOST is not set."
    exit 1
  fi
  if [[ ! -d "$backend_dir" ]]; then
    echo "Error: Backend directory does not exist: $backend_dir"
    exit 1
  fi
  echo "Uploading backend (excluding vendor, node_modules, .env, .secrets) -> $remote_path ..."
  lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
    set ftp:ssl-allow no;
    set net:timeout 30;
    mkdir -p $remote_path;
    lcd $backend_dir;
    mirror -R -v -e --delete -x '^vendor/' -x '^node_modules/' -x '^\.env' -x '^\.env\.' -x '^\.secrets' -x '^storage/' -x '^database/database\.sqlite$' -x '^storage/logs/.*\.log$' -x '^\.git/' -x '^\.phpunit' -x '^\.idea/' -x '^\.vscode/' -x '^public/hot' -x '^public/build' -x '^public/storage' . $remote_path;
    bye
  "
  echo "Backend upload done."
}

# --- Upload frontend dist ---
upload_frontend() {
  ftp_put_generic "$PROJECT_ROOT/frontend/dist-deploy" "$REMOTE_FRONTEND_PATH"
}

# --- Upload a single file ---
# Usage: ftp_put_file "local_file" "remote_path"
# remote_path is full path from FTP root, e.g. "prod/backend/.env"
ftp_put_file() {
  local local_file="$1"
  local remote_path="$2"
  if [[ -z "$FTP_HOST" ]]; then
    echo "Error: FTP_HOST is not set."
    exit 1
  fi
  if [[ ! -f "$local_file" ]]; then
    echo "Error: Local file does not exist: $local_file"
    exit 1
  fi
  local_file="$(cd "$(dirname "$local_file")" && pwd)/$(basename "$local_file")"
  local remote_dir
  local remote_name
  remote_dir="${remote_path%/*}"
  remote_name="${remote_path##*/}"
  if [[ "$remote_dir" == "$remote_path" ]]; then
    remote_dir="."
    remote_name="$remote_path"
  fi
  echo "Uploading $local_file -> ftp://$FTP_HOST/$remote_path ..."
  lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
    set ftp:ssl-allow no;
    set net:timeout 30;
    mkdir -p $remote_dir;
    cd $remote_dir;
    put $local_file -o $remote_name;
    bye
  "
  echo "Upload done: $remote_path"
}

# --- Upload .env to server: build from .env.production and inject DB from .secrets ---
upload_env() {
  local env_file="$PROJECT_ROOT/backend/.env.production"
  if [[ ! -f "$env_file" ]]; then
    print_error "$env_file not found. Create it with production backend values."
    exit 1
  fi
  read_secrets_keyval
  local tmp_env
  tmp_env="$(mktemp)"
  _CLEANUP_SCRIPT="$tmp_env"
  trap 'rm -f "$_CLEANUP_SCRIPT"' RETURN
  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" =~ ^DB_DATABASE= ]]; then
      echo "DB_DATABASE=${SECRETS_db:-}"
    elif [[ "$line" =~ ^DB_USERNAME= ]]; then
      echo "DB_USERNAME=${SECRETS_mysql_user:-}"
    elif [[ "$line" =~ ^DB_PASSWORD= ]]; then
      if [[ -n "${SECRETS_mysql_password:-}" ]]; then
        echo "DB_PASSWORD=\"$SECRETS_mysql_password\""
      else
        echo "DB_PASSWORD="
      fi
    else
      echo "$line"
    fi
  done < "$env_file" > "$tmp_env"
  ftp_put_file "$tmp_env" "$REMOTE_BACKEND_PATH/.env"
}

# --- Main ---
main() {
  local install_deps_flag=0
  if [[ "${1:-}" == "--install-deps" ]]; then
    install_deps_flag=1
    shift
  fi
  local mode="${1:-all}"
  export INSTALL_NPM_DEPS="$install_deps_flag"
  read_ftp_credentials
  check_lftp

  case "$mode" in
    backend)
      upload_backend
      ;;
    frontend)
      build_frontend
      upload_frontend
      ;;
    all|"")
      build_frontend
      upload_frontend
      upload_backend
      ;;
    env)
      upload_env
      ;;
    file)
      local local_file="${2:-}"
      local remote_path="${3:-}"
      if [[ -z "$local_file" ]]; then
        print_error "Missing argument: local_file"
        echo "Usage: $0 file <local_file> [remote_path]"
        exit 1
      fi
      if [[ -z "$remote_path" ]]; then
        remote_path="$REMOTE_BACKEND_PATH/$(basename "$local_file")"
      fi
      ftp_put_file "$local_file" "$remote_path"
      ;;
    list)
      if [[ -z "${2:-}" ]]; then
        print_error "Missing argument: remote_path"
        echo "Usage: $0 list <remote_path>"
        echo "  e.g. $0 list prod/backend   or   $0 list prod/backend/app/"
        exit 1
      fi
      ftp_list "$2"
      ;;
    cat)
      if [[ -z "${2:-}" ]]; then
        print_error "Missing argument: remote_path"
        echo "Usage: $0 cat <remote_path>"
        echo "  e.g. $0 cat prod/backend/.env"
        exit 1
      fi
      ftp_cat "$2"
      ;;
    put)
      if [[ -z "${2:-}" || -z "${3:-}" ]]; then
        print_error "Missing arguments: source and destination"
        echo "Usage: $0 put <source> <destination>"
        echo "  Upload file or folder. If destination ends with /, source name is used."
        echo "  e.g. $0 put backend/.env.production prod/backend/.env"
        echo "  e.g. $0 put frontend/dist/ prod/frontend/"
        exit 1
      fi
      ftp_put_generic "$2" "$3"
      ;;
    *)
      echo "Usage: $0 [--install-deps] [backend|all|env|file|list|cat|put] [args...]"
      echo ""
      echo "Deploy:"
      echo "  all      (default) Build frontend, upload frontend + backend"
      echo "  frontend Build frontend and upload frontend only"
      echo "  backend  Upload backend only"
      echo "  env      Upload .env.production to server as backend/.env"
      echo "  file <local_file> [remote_path]  Upload a single file"
      echo "  --install-deps  Run npm install before frontend build (off by default)"
      echo ""
      echo "FTP (list / read / upload any path):"
      echo "  list <remote_path>       List remote directory"
      echo "  cat <remote_path>         Read remote file"
      echo "  put <source> <destination>  Upload file or folder"
      exit 1
      ;;
  esac

  print_success "Done."
}

main "$@"
