scp c/Users/Lenovo/Documents/Classes/CSCI_375/375Labs/school.sql bpaudel2@turing.cs.olemiss.edu:~/projects/csci375

oleMissF25
oriionS143

mysql -u bpaudel2 -p

CREATE DATABASE emp_skill_db;
USE emp_skill_db;
SOURCE /home/john2/fold/emp_skill.sql;

create database lab1;
use mylabdb;

SHOW DATABASES;

tee PaudelLab1Output.txt;
notee;

from outside:
scp bpaudel2@turing.cs.olemiss.edu:~/projects/csci375/PaudelLab1Output.txt /mnt/c/Users/Lenovo/Documents/Classes/CSCI_375/375Labs/

vertabelo erd diagrams
new->phys data model->mysql->