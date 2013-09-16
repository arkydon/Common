DROP TABLE IF EXISTS doc;

CREATE TABLE doc (
    doc_id INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    counter INTEGER NOT NULL
);

INSERT INTO doc (title, author, counter) VALUES
('Divine Songs', 'Isaac Watts', 100),
('The Governess, or The Little Female Academy', 'Sarah Fielding', 700),
('The Parables of Our Lord and Saviour Jesus Christ', 'Christopher Smart', 120),
('Hymns for the Amusement of Children', 'Christopher Smart', 400),
('An Easy Introduction to the Knowledge of Nature', 'Sarah Trimmer', 200),
('Hymns in Prose for Children', 'Anna Laetitia Barbauld', 200),
('The Life and Perambulation of a Mouse', 'Dorothy Kilner', 890),
('Cobwebs to Catch Flies', 'Ellenor Fenn', 1040),
('Anecdotes of a Boarding School', 'Dorothy Kilner', 90),
('The Female Guardian', 'Ellenor Fenn', 450),
('A Description of a Set of Prints of Scripture History', 'Sarah Trimmer', 70),
('Fabulous Histories', 'Sarah Trimmer', 690),
('The History of Little Jack', 'Thomas Day', 170),
('Original Stories from Real Life', 'Mary Wollstonecraft', 120),
('Keeper’s Travels in Search of His Master', 'Edward Augustus Kendall', 200),
('The Rational Brutes', 'Dorothy Kilner', 900),
('Moral Tales for Young People', 'Maria Edgeworth', 670);
