# Wappi: Whatsapp и Telegram уведомления WooCommerce

**Авторы:** support@wappi.pro  
**Теги:** woo commerce, woocommerce, ecommerce, whatsapp, telegram, notification, whatsapp notification, telegram notification, уведомления  
**Минимальная версия:** 3.8  
**Протестировано до версии:** 6.7.1  
**Стабильная версия:** 1.0.7  
**Требуется PHP:** 7.4  
**Лицензия:** GPLv2 или более поздняя  
**URI лицензии:** http://www.gnu.org/licenses/gpl-2.0.html  

Автоматические уведомления Whatsapp и Telegram о статусах заказов для администраторов и покупателей интернет-магазина WooCommerce.

## Описание

С помощью плагина "Wappi: Whatsapp и Telegram уведомления WooCommerce" вы можете отправлять автоматические уведомления Whatsapp и Telegram о статусах заказов администраторам и покупателям интернет-магазина, работающего на платформе WordPress WooCommerce.

Обратите внимание: это НЕ Whatsapp Business API (WABA), а подключение вашего собственного аккаунта Whatsapp через сканирование QR-кода.

Возможности:

* Поддержка модуля Wordpress WooCommerce.
* Отправка уведомлений с вашего собственного аккаунта Whatsapp. Подключите ваш личный аккаунт Whatsapp, отсканировав QR-код, и отправляйте с него автоматические сообщения.
* Отправка уведомлений с вашего собственного аккаунта Telegram. Подключите ваш личный аккаунт Telegram (не бот) и отправляйте с него автоматические сообщения.
* Автоматические уведомления администраторам. Отправка уведомлений администратору или продавцу о новых заказах и смене статусов заказа. Можно указать несколько произвольных номеров.
* Автоматические уведомления покупателям. Отправка уведомлений покупателям о подтверждении заказа и смене статусов заказа.
* Установка индивидуальных шаблонов уведомлений с переменными. Для каждого уведомления можно установить свой собственный текст с использованием переменных: продукты, количество, цена, имя, телефон, адрес, произвольные поля (трек-номер).

[САЙТ](https://wappi.pro/) | [Поддержка](https://t.me/wappi_support)

## Установка

1. Убедитесь, что у вас установлена последняя версия плагина [WooCommerce](http://www.woothemes.com/woocommerce).
2. Существует несколько вариантов установки плагина:
    * Через каталог плагинов:
        - В административной панели перейдите на страницу *Плагины* и нажмите *Добавить новый*.
        - Найдите плагин "Wappi: Whatsapp и Telegram уведомления WooCommerce".
        - Нажмите кнопку *Установить*.
    * Через консоль:
        - Скачайте плагин здесь: https://wappi.pro/integrations/wordpress.
        - В административной панели перейдите на страницу *Плагины* и нажмите *Добавить новый*.
        - Перейдите на вкладку *Загрузить*, нажмите *Обзор* и выберите архив с плагином. Нажмите *Установить*.
    * По FTP:
        - Скачайте плагин здесь: https://wappi.pro/integrations/wordpress.
        - Распакуйте архив и загрузите содержимое по FTP в папку your-domain/wp-content/plugins.
        - В административной панели перейдите на страницу *Плагины* и нажмите *Установить* рядом с появившимся плагином.
3. После установки плагина нажмите *Активировать плагин*.
4. Наведите курсор на пункт меню *WooCommerce* и выберите *Wappi*.
5. В настройках введите Токен API и ID профиля (найти можно на https://wappi.pro/dashboard), а также номер Whatsapp продавца.
6. Если это необходимо, укажите статусы для каждого вида уведомлений и текст.
7. Нажмите кнопку *Сохранить*.

## Журнал изменений

### 1.0

- Первая версия

### 1.0.1

- Добавлено требование плагин WooCommerce
- Добавлены переменные для плагина "Отслеживание заказов для WooCommerce"
- Добавлено многократное число строк в шаблонах
- Тестирование до версии 6.6.1 WordPress

### 1.0.2

- Добавлена обработка пользовательских переменных заказа

### 1.0.3

- Увеличена максимальная длина сообщения с 670 до 5000 символов

### 1.0.4

- Добавлены переменные SHIPPING_METHOD и PAYMENT_METHOD
- Тестирование до версии: 6.6.2 WordPress

### 1.0.5

- Добавлена валидация для нероссийских номеров телефонов
- Тестирование до версии: 6.7.1 WordPress

### 1.0.6

- Обновлён метод получения заказа

### 1.0.7

- Добавлены каскады