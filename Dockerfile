FROM uselagoon/php-8.1-cli

COPY . /app

# Grab kubectl
RUN curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl" && \
    curl -LO "https://dl.k8s.io/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl.sha256" && \
    echo "$(cat kubectl.sha256)  kubectl" | sha256sum --check && \
    chmod +x kubectl && mv kubectl /usr/local/bin

RUN curl -L "https://github.com/uselagoon/lagoon-cli/releases/download/v0.15.2/lagoon-cli-v0.15.2-linux-amd64" -o /usr/local/bin/lagoon && chmod +x /usr/local/bin/lagoon

RUN wget -O /usr/bin/lagoon-sync https://github.com/uselagoon/lagoon-sync/releases/download/v0.6.1/lagoon-sync_0.6.1_linux_amd64 && chmod +x /usr/bin/lagoon-sync && true

RUN composer install && touch /app/.lagoon.yml

CMD lagoon config add --create-config -g $TASK_API_HOST/graphql -H $TASK_SSH_HOST -P $TASK_SSH_PORT -l amazeeio --force && /app/vendor/bin/robo run