ALL PEOPLE, ORDERED BY LAST NAME
--------------------------------
SELECT TITLE, LAST_NAME, FIRST_NAME, COUNTRY, DESCRIPTION, QUALIFICATIONS, CHECKED, PUBLISHED FROM person ORDER BY LAST_NAME, FIRST_NAME;

-- STATISTICS
SELECT 'Persons' AS `CATEGORY`, COUNT(*) AS `COUNT`, 'Total number of people in the database' AS DESCRIPTION FROM person UNION
SELECT 'Publications', COUNT(*), 'Total number of publications in the database' FROM publication UNION
SELECT 'Declarations', COUNT(*), 'Total number of public declarations in the database' FROM declaration UNION
SELECT 'Quotations', COUNT(*), 'Total number of quotations in the database' FROM quotation UNION
SELECT 'Professors', COUNT(*), 'Number of university professors (past or present)' FROM person WHERE TITLE='Prof.' UNION
SELECT 'Doctorates', COUNT(*), 'Number qualified to doctoral or higher level' FROM person WHERE TITLE='Dr.' UNION
SELECT 'Meteorologists', COUNT(*), 'Number of qualified meterologists' FROM person WHERE DESCRIPTION LIKE '%meteorolog%' OR DESCRIPTION LIKE '%weather%' UNION
SELECT 'Climatologists', COUNT(*), 'Number of climatologists' FROM person WHERE DESCRIPTION LIKE '%climatolog%' UNION
SELECT 'IPCC', COUNT(*), 'Number of scientists who work(ed) for IPCC' FROM person WHERE DESCRIPTION LIKE '%IPCC%' AND DESCRIPTION NOT LIKE '%NIPCC%' UNION
SELECT 'NASA', COUNT(*), 'Number of scientists who work(ed) for NASA' FROM person WHERE DESCRIPTION LIKE '%NASA%' UNION
SELECT 'NOAA', COUNT(*), 'Number of scientists who work(ed) for NOAA' FROM person WHERE DESCRIPTION LIKE '%NOAA%' UNION
SELECT 'Nobel Laureates', COUNT(*), 'Number of Nobel prize recipients' FROM person WHERE DESCRIPTION LIKE '%Nobel%' AND DESCRIPTION NOT LIKE '%Akzo%' UNION
SELECT 'Published', COUNT(*), 'Number of scientists who have published peer-reviewed science' FROM person WHERE PUBLISHED UNION
SELECT 'Checked', COUNT(*), 'Number of scientists whose credentials have been checked' FROM person WHERE CHECKED;

-- PERSON COUNT BY RATING
SELECT RATING, COUNT(*) FROM person GROUP BY RATING ORDER BY RATING DESC;

-- FIND DUPLICATES
SELECT TITLE, FIRST_NAME, LAST_NAME, COUNT(*) AS `COUNT` FROM person GROUP BY TITLE, FIRST_NAME, LAST_NAME HAVING `COUNT` > 1 ORDER BY LAST_NAME, FIRST_NAME;

-- SCIENTISTS BY COUNTRY
SELECT COUNTRY, COUNT(*) AS `COUNT` FROM person GROUP BY COUNTRY ORDER BY `COUNT` DESC;

-- SET UP RATINGS
UPDATE person SET RATING = 0;
UPDATE person SET RATING = RATING + 1 WHERE TITLE = 'Prof.' OR TITLE = 'Dr.';
UPDATE person SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%Nobel%' AND DESCRIPTION NOT LIKE '%Akzo%';
UPDATE person SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%IPCC%' OR DESCRIPTION LIKE '%NASA%';
UPDATE person SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%meteorolog%' OR DESCRIPTION LIKE '%weather%' OR DESCRIPTION LIKE '%climat%';
UPDATE person SET RATING = RATING + 1 WHERE DESCRIPTION LIKE '%astro%' OR DESCRIPTION LIKE '%atmospher%' OR DESCRIPTION LIKE '%earth%' OR DESCRIPTION LIKE '%geo%';
UPDATE person SET RATING = RATING + 1 WHERE PUBLISHED;
UPDATE person SET RATING = 5 WHERE RATING > 5;


SELECT RATING, COUNT(*) FROM person GROUP BY RATING ORDER BY RATING;

-- Doctorates
SELECT * FROM person WHERE TITLE='Dr.' OR QUALIFICATIONS LIKE '%Ph.D.%' OR QUALIFICATIONS LIKE '%D.Phil.%' OR QUALIFICATIONS LIKE '%Dr.%' OR QUALIFICATIONS LIKE '%D.Sci.%' OR QUALIFICATIONS LIKE '%Sc.D.%' OR QUALIFICATIONS LIKE '%Doctorate%';