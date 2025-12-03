CREATE DATABASE flash_sale;
CREATE USER 'flash_user'@'localhost' IDENTIFIED BY '123456';
GRANT ALL PRIVILEGES ON flash_sale.* TO 'flash_user'@'localhost';
FLUSH PRIVILEGES;