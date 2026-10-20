# Ansible for the server playbooks (ADR-0019), versions pinned in requirements.txt and requirements.yml.
FROM python:3.14-alpine

RUN apk add --no-cache openssh-client

COPY ansible/requirements.txt ansible/requirements.yml /tmp/

RUN pip install --no-cache-dir -r /tmp/requirements.txt \
    && ansible-galaxy collection install -r /tmp/requirements.yml -p /usr/share/ansible/collections \
    && rm /tmp/requirements.txt /tmp/requirements.yml

ENV ANSIBLE_CONFIG=/var/www/balise/ansible/ansible.cfg

WORKDIR /var/www/balise/ansible
