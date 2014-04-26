RPMBUILD=rpmbuild
SPECFILE=wp-cli.spec
BUILD := $(shell grep -i "Release:" $(PWD)/$(SPECFILE) |awk -F":" '{print $$2 + 1}')
TMPBUILD=/tmp/wp-cli-rpm
rpm:
		rm -rf $(TMPBUILD)
		mkdir -p $(TMPBUILD)
		cp -af $(PWD) $(TMPBUILD)/wp-cli
		composer -d=$(TMPBUILD)/wp-cli install
		rm -rf $(TMPBUILD)/wp-cli/dist
		rm -rf $(TMPBUILD)/wp-cli/rpms
		find $(TMPBUILD) -depth -type d -name ".git" -exec rm -rf {} \;
		find $(TMPBUILD) -depth -type f -name ".git*" -exec rm -rf {} \;
		cd $(TMPBUILD) && tar -cf $(TMPBUILD)/wp-cli.tar wp-cli && cd $(PWD)
		gzip $(TMPBUILD)/wp-cli.tar
		mv $(TMPBUILD)/wp-cli/$(SPECFILE) $(TMPBUILD)/$(SPECFILE)
		rm -rf $(TMPBUILD)/wp-cli

		mkdir -p $(TMPBUILD)/dist/BUILD \
				$(TMPBUILD)/dist/RPMS \
				$(TMPBUILD)/dist/SPECS \
				$(TMPBUILD)/dist/SOURCES \
				$(TMPBUILD)/dist/TMP \
				$(TMPBUILD)/dist/install \
				$(TMPBUILD)/dist/SRPMS
		mkdir -p $(PWD)/rpms
		cp -f `find $(TMPBUILD) -maxdepth 1 -type f ` $(TMPBUILD)/dist/SOURCES/
		$(RPMBUILD) -bb \
				--define "_topdir $(TMPBUILD)/dist" \
				--define "buildroot $(TMPBUILD)/dist/install" \
				$(TMPBUILD)/$(SPECFILE)
		@echo "The following RPMs were created (in rpms/ dir): " ; \
				find $(TMPBUILD)/dist -name \*.rpm -printf "%f\n"
		@mv -f $(TMPBUILD)/dist/RPMS/*/* $(PWD)/rpms/
		@rm -rf $(TMPBUILD)
clean:
		@rm -rf $(PWD)/dist
		@rm -rf $(PWD)/rpms
