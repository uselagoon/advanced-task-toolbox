FROM uselagoon/php-8.1-cli

COPY . /app

# Grab kubectl
RUN curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl" && \
    curl -LO "https://dl.k8s.io/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl.sha256" && \
    echo "$(cat kubectl.sha256)  kubectl" | sha256sum --check && \
    chmod +x kubectl && mv kubectl /usr/local/bin

RUN curl -L "https://github.com/uselagoon/lagoon-cli/releases/download/v0.12.5/lagoon-cli-v0.12.5-linux-amd64" -o /usr/local/bin/lagoon && chmod +x /usr/local/bin/lagoon


RUN composer install

CMD /app/vendor/bin/robo run /app/migrate.yaml
