create table articles
(
    id        INTEGER
        primary key autoincrement,
    author_id INTEGER,
    title     VARCHAR,
    body      TEXT,
    published VARCHAR(1) default 'N'
);

create table authors
(
    author_id INTEGER
        primary key autoincrement,
    name      VARCHAR
);

create table comments
(
    id         INTEGER
        primary key autoincrement,
    article_id INTEGER not null,
    user_id    INTEGER not null,
    comment    TEXT,
    published  VARCHAR(1) default 'N',
    created    DATETIME,
    updated    DATETIME
);

create table tags
(
    id   INTEGER not null
        primary key autoincrement,
    name VARCHAR not null
);

create table articles_tags
(
    article_id INTEGER not null,
    tag_id     INTEGER not null
        constraint tag_id_fk
            references tags
            on update cascade on delete cascade,
    constraint unique_tag
        primary key (article_id, tag_id)
);

create table users
(
    id       INTEGER
        primary key autoincrement,
    username VARCHAR,
    password VARCHAR,
    created  TIMESTAMP default NULL,
    updated  TIMESTAMP default NULL
);

