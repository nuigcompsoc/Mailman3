package main

import (
	"crypto/tls"
	"fmt"
	"log"
	"os"
	"os/exec"
	"strings"

	ldap "github.com/go-ldap/ldap/v3"
)

func getEmails() []string {
	bindusername := "cn=readonlyuser,dc=example"
	bindpassword := "JimLovesRashers12*"

	l, err := ldap.Dial("tcp", fmt.Sprintf("%s:%d", "ldap.example.com", 389))
	if err != nil {
		log.Fatal(err)
	}
	defer l.Close()

	// Reconnect with TLS
	err = l.StartTLS(&tls.Config{InsecureSkipVerify: true})
	if err != nil {
		log.Fatal(err)
	}

	// First bind with a read only user
	err = l.Bind(bindusername, bindpassword)
	if err != nil {
		log.Fatal(err)
	}

	// Search for the given username
	searchRequest := ldap.NewSearchRequest(
		"ou=group,dc=example",
		ldap.ScopeWholeSubtree, ldap.NeverDerefAliases, 0, 0, false,
		"(cn="+os.Args[1]+")",
		[]string{"memberUid"},
		nil,
	)

	sr, err := l.Search(searchRequest)
	if err != nil {
		log.Fatal(err)
	}

	members := sr.Entries[0].GetAttributeValues("memberUid")

	for i := 0; i < len(members); i++ {
		members[i] = members[i] + "@compsoc.ie"
	}

	return members
}

func writeEmailsToFile(emails []string) string {
	emailString := ""

	for i := 0; i < len(emails); i++ {
		emailString += emails[i] + "\n"
	}

	os.Remove("/opt/mailman/mailman-emails.txt")
	f, err := os.OpenFile("/opt/mailman/mailman-emails.txt", os.O_CREATE|os.O_WRONLY, 0600)
	_, err = f.Write([]byte(emailString))
	if err != nil {
		panic(err)
	}

	return "/opt/mailman/mailman-emails.txt"
}

func mailmanSync(filename string) {
	out, err := exec.Command("/bin/bash", "/opt/mailman/venv/bin/mailman", "members", "-s", filename, os.Args[2]).Output()

	if err != nil {
		log.Fatalf("cmd.Run() failed with %s\n", err)
		log.Fatal(string(out))
		log.Fatal(err)
	}

	if !strings.Contains(string(out), "Nothing to do") {
		fmt.Println(string(out))
	}
}

func main() {
	emails := getEmails()
	filename := writeEmailsToFile(emails)
	mailmanSync(filename)
	os.Remove(filename)
}
