#!/bin/sh
# Local stand-in for the server (E1.3b, ADR-0019): an OrbStack Ubuntu machine, arm64 like the Oracle A1 instance,
# reached over SSH with its own key, as Ansible reaches Oracle. Runs on the host (OrbStack's `orb` command).
#   sh scripts/local-server.sh      # create it, and its SSH key in ansible/local/
#   orb delete -f balise-server     # remove it
set -eu

name=balise-server
key=ansible/local/id_ed25519
ubuntu=$(sed -n 's/^ubuntu: "\(.*\)"$/\1/p' versions.yaml)

mkdir -p ansible/local
[ -f "$key" ] || ssh-keygen -q -t ed25519 -N '' -C "$name" -f "$key"

user_data=$(mktemp)
trap 'rm -f "$user_data"' EXIT

# What Oracle's Ubuntu image already has: an SSH server and the admin user's key.
cat > "$user_data" <<USERDATA
#cloud-config
packages: [openssh-server]
users:
  - name: ubuntu
    sudo: ALL=(ALL) NOPASSWD:ALL
    shell: /bin/bash
    ssh_authorized_keys:
      - $(cat "$key.pub")
USERDATA

orb create --isolated --memory 4G --arch arm64 --user ubuntu --user-data "$user_data" "ubuntu:$ubuntu" "$name"
