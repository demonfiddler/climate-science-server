ALL PEOPLE, ORDERED BY LAST NAME
--------------------------------
SELECT TITLE, LAST_NAME, FIRST_NAME, COUNTRY, DESCRIPTION, QUALIFICATIONS, CHECKED, PUBLISHED FROM person ORDER BY LAST_NAME, FIRST_NAME;

-- STATISTICS
SELECT 'All' AS `CATEGORY`, COUNT(*) AS `COUNT` FROM person UNION
SELECT 'Professors', COUNT(*) FROM person WHERE TITLE='Prof.' UNION
SELECT 'Doctorates', COUNT(*) FROM person WHERE TITLE='Dr.' UNION
SELECT 'Meteorologists', COUNT(*) FROM person WHERE DESCRIPTION LIKE '%eteorolog%' OR DESCRIPTION LIKE '%eather%' UNION
SELECT 'Climatologists', COUNT(*) FROM person WHERE DESCRIPTION LIKE '%limatolog%' UNION
SELECT 'IPCC', COUNT(*) FROM person WHERE DESCRIPTION LIKE '%IPCC%' UNION
SELECT 'Nobel Laureates', COUNT(*) FROM person WHERE DESCRIPTION LIKE '%Nobel%' AND DESCRIPTION NOT LIKE '%Akzo%' UNION
SELECT 'Published', COUNT(*) FROM person WHERE PUBLISHED UNION
SELECT 'Checked', COUNT(*) FROM person WHERE CHECKED;

-- FIND DUPLICATES
SELECT TITLE, FIRST_NAME, LAST_NAME, COUNT(*) AS `COUNT` FROM person GROUP BY TITLE, FIRST_NAME, LAST_NAME HAVING `COUNT` > 1 ORDER BY LAST_NAME, FIRST_NAME;

-- SCIENTISTS BY COUNTRY
SELECT COUNTRY, COUNT(*) AS `COUNT` FROM person GROUP BY COUNTRY ORDER BY `COUNT` DESC;

-- SET UP RATINGS
UPDATE PERSON SET RATING = 0;
UPDATE PERSON SET RATING = RATING + 1 WHERE TITLE = 'Prof.' OR TITLE = 'Dr.';
UPDATE PERSON SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%Nobel%' AND DESCRIPTION NOT LIKE '%Akzo%';
UPDATE PERSON SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%IPCC%' OR DESCRIPTION LIKE '%NASA%';
UPDATE PERSON SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%eteorolog%' OR DESCRIPTION LIKE '%eather%';
UPDATE PERSON SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%astro%' OR DESCRIPTION LIKE '%atmospher%' OR DESCRIPTION LIKE '%climat%' OR DESCRIPTION LIKE '%earth%' OR DESCRIPTION LIKE '%geo%' OR DESCRIPTION LIKE '%meteorolog%';
UPDATE PERSON SET RATING = RATING + 1 WHERE PUBLISHED;

SELECT RATING, COUNT(*) FROM person GROUP BY RATING ORDER BY RATING;

-- Doctorates
SELECT * FROM person WHERE TITLE='Dr.' OR QUALIFICATIONS LIKE '%Ph.D.%' OR QUALIFICATIONS LIKE '%D.Phil.%' OR QUALIFICATIONS LIKE '%Dr.%' OR QUALIFICATIONS LIKE '%D.Sci.%' OR QUALIFICATIONS LIKE '%Sc.D.%' OR QUALIFICATIONS LIKE '%Doctorate%';