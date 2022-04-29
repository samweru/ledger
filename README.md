Ledger
===

This is a bookkeeping cli concept.

# Setup 

## Tasker

Tasker is task manager. In this case it is used to run database tasks.

### Install Tasker

```
wget https://github.com/samweru/strukt-tasker/releases/download/v1.0.0-alpha/tasker.phar #download
chmod a+x tasker.phar #make executable
mv tasker.phar tasker #rename
```

### Seed DB

Before you start you'll need to seed the database.

```
./tasker db:seed
```

### Other Tasker Commands

```
db:clean 			# Delete Database
db:ls 				# Show Tables
db:show <table>	# Show Table Rows
```

# Usage

Go into shell.

```sh
php book.php
```

## Book.php Commands

Help commands.

```
sch ?
trx ?
bal ?
```

`book.php` uses double entry accounting accrual basis strategy.

 - Schedule command `sch` is used to prepare a transaction
 - Transaction command `trx` is used to fulfil that transaction

Enjoy!

