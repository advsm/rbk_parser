create database `dummy`;
use dummy;

create table `news` (
   `id` int(11) not null auto_increment,
   `name` varchar(256) collate utf8_unicode_ci not null,
   `promo` varchar(512) collate utf8_unicode_ci not null,
   `url` varchar(256) collate utf8_unicode_ci not null,
   `image_url` varchar(256) collate utf8_unicode_ci not null,
   `text` text collate utf8_unicode_ci null,
   `created_at` datetime not null,
   `modified_at` timestamp not null default current_timestamp on update current_timestamp,
    unique key (`url`),
    primary key (`id`)
) engine=InnoDB default charset=utf8 collate=utf8_unicode_ci;