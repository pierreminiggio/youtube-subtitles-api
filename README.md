# youtube-subtitles-api

Migration :
```sql
--
-- Structure de la table `unprocessable_request`
--

CREATE TABLE `unprocessable_request` (
  `id` int(11) NOT NULL,
  `request` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `unprocessable_request`
--
ALTER TABLE `unprocessable_request`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `unprocessable_request`
--
ALTER TABLE `unprocessable_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE `video` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `youtube_id` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

CREATE TABLE `language` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

CREATE TABLE `subtitle` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `video_id` INT(11) NOT NULL,
  `language_id` INT(11) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

COMMIT;
```
