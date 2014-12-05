phpcs:
	phpcs --standard=PSR2 --extensions=php --ignore=vendor/* --warning-severity=0 ./
phpcbf:
	phpcbf --standard=PSR2 --extensions=php --ignore=vendor/* --warning-severity=0 ./