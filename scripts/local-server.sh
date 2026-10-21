#!/bin/sh
# Local stand-ins for the server (ADR-0020 point 9): OrbStack machines reached over SSH with their own key, as the
# bootstrap reaches a real host. Runs on the host (OrbStack's `orb` command); the inventory is ansible/inventory/local.yml.
#   sh scripts/local-server.sh debian    # balise-debian: a fresh Debian 13
#   sh scripts/local-server.sh ubuntu    # balise-ubuntu: a fresh Ubuntu 26.04
#   sh scripts/local-server.sh shared    # balise-shared: a Debian 13 already running Docker, nginx and another site
#   orb delete -f balise-debian          # remove one
set -eu

packages="openssh-server, python3"
case "${1:-}" in
    debian) distro=debian:trixie ;;
    ubuntu) distro=ubuntu:resolute ;;
    shared) distro=debian:trixie; packages="$packages, docker.io, docker-compose, nginx" ;;
    *) echo "Usage: sh scripts/local-server.sh debian|ubuntu|shared" >&2; exit 1 ;;
esac

name="balise-$1"
key=ansible/local/id_ed25519

mkdir -p ansible/local
[ -f "$key" ] || ssh-keygen -q -t ed25519 -N '' -C balise-local -f "$key"

user_data=$(mktemp)
trap 'rm -f "$user_data"' EXIT

# What a rented server already has: an SSH server and an admin user who can sudo.
cat > "$user_data" <<USERDATA
#cloud-config
packages: [$packages]
users:
  - name: admin
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    ssh_authorized_keys:
      - $(cat "$key.pub")
USERDATA

# The shared host serves another site from nginx and another container from Docker, which the bootstrap must leave alone.
if [ "$1" = shared ]; then
    cat >> "$user_data" <<'USERDATA'
write_files:
  - path: /etc/nginx/conf.d/other.conf
    content: |
      server {
          listen 80;
          server_name other.local;
          location / { return 200 "other site\n"; }
      }
runcmd:
  - systemctl reload nginx
  - docker run -d --name other --restart unless-stopped -p 127.0.0.1:8081:80 nginx:alpine
USERDATA
fi

# Not --isolated: in an isolated machine, the kernel refuses Debian 13 runc 1.1's device filter and no container starts.
orb create --memory 4G --user admin --user-data "$user_data" "$distro" "$name"
