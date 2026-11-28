ALTER TABLE `scrape_sources`
  ADD COLUMN `collection_method` varchar(16) NOT NULL DEFAULT 'http' AFTER `listing_url`;
