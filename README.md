# Auto Unassign Subject Tagger Module

This module automatically unassigns the agent from a conversation when a customer replies. The conversation becomes active again and can be picked up by another agent. Additionally, it updates the email subject by adding (Ticket#<ID> <original___subject>) to help support teams track and manage tickets more efficiently.

## Langkah 1: Download Module

### Catatan: 
Lokasi direktori fresscout/Modules/ bisa berbeda tergantung di mana kamu menginstal Fresscout.
Contoh umum direktori root Fresscout:

    /var/www/html/fresscout/

    /opt/fresscout/

    /home/user/public_html/fresscout/

Pastikan kamu berada di direktori Modules sesuai dengan lokasi instalasi Fresscout kamu sebelum menjalankan perintah di bawah ini.


### Clone via Git

    git clone https://github.com/juonjr25/Auto-Unassign-Subject-Tagger-Module.git AutoUnassign

## Langkah 2: Clear Cache

    php artisan config:clear
    php artisan cache:clear

## Langkah 3: Aktifkan Module dari Web Interface
1. Login ke dashboard Fresscout (misalnya http://yourdomain.com)

2. Buka menu Admin Panel → Modules

3. Cari module bernama Auto Unassign Subject Tagger
![Screenshot_2](https://github.com/user-attachments/assets/53024882-96f6-4e49-9582-aa851f5b41e1)

4. Klik tombol Enable atau Install sesuai yang muncul
   ![Screenshot_4](https://github.com/user-attachments/assets/b4acc8d2-4d14-4c0d-ad35-032df260e145)
