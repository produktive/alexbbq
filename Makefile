CC=gcc
CFLAGS=-Wall -Wextra -O2
LDFLAGS=-lpigpio -lrt -lpthread -lsqlite3

maverick: maverick.c
	$(CC) $(CFLAGS) -o maverick maverick.c $(LDFLAGS)

clean:
	rm -f maverick
