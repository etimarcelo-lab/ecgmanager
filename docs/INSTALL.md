# ECG Manager - Guia de Instalação

## Requisitos do Sistema

### Servidor
- Debian 11/12 ou Ubuntu 20.04/22.04
- Apache 2.4+
- PHP 7.4+ (recomendado 8.0+)
- MariaDB 10.5+ ou MySQL 8.0+
- Python 3.8+
- 2GB RAM mínimo
- 10GB espaço em disco

### Dependências PHP
```bash
sudo apt install -y \
    apache2 \
    mariadb-server \
    php \
    php-mysql \
    php-curl \
    php-gd \
    php-mbstring \
    php-xml \
    php-zip \
    php-bcmath \
    php-intl \
    libapache2-mod-php \
    python3 \
    python3-pip \
    python3-mysqldb