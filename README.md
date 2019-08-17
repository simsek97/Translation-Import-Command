# TranslationImportCommand.php
Translation Import Command for Symfony 4
This command can be used to read csv files from a local directory and import them into Symfony project (i.e. translations directory)

## Local csv files

```
|-- Users
|   |-- simsek
|   |    |-- translations
|   |    |   |-- messages.en.csv
|   |    |   |-- messages.es.csv
|   |    |   |-- messages.tr.csv
```

scv files should have key and translation in each line in the format "key","translation" like below
```
"title","Title"
"author","Author"
```

## Usage

From project folder run the command

```
./bin/console app:translation:import --path=/Users/simsek/translations --input=csv --output=xlf
```

That is it. You should see your translation files under translations folder in your project

```
|-- translations
|   |-- messages.en.xlf
|   |-- messages.es.xlf
|   |-- messages.tr.xlf
```
