%define source0    wp-cli.tar.gz
%define name       wpaas-wp-cli
%define version    0.14.0

Name:          %{name}
Summary:       wp-cli %{version} for WordPress
Version:       %{version}
Release:       1
Group:         WordPress
License:       MIT
URL:           http://wp-cli.org/
Packager:      Kurt Payne <kpayne@godaddy.com>
Source:        %{source}
BuildRoot:     %{_tmppath}/%{name}-%{version}-%{release}-buildroot
Prefix:        %{_prefix}
Provides:      %{name}
Obsoletes:     wp-cli
BuildRequires: php

%description
WP-CLI is a set of command-line tools for managing WordPress
installations. You can update plugins, set up multisite
installs, create posts and much more.

%install
%{__mkdir_p} $RPM_BUILD_ROOT/opt
cd $RPM_BUILD_ROOT/opt
tar -xzf $RPM_SOURCE_DIR/%{source0}
rm -f $RPM_SOURCE_DIR/%{source0}/Makefile
rm -f $RPM_SOURCE_DIR/%{source0}/wp-cli.spec

# Generate file manifest
cd $RPM_BUILD_ROOT
find . -type f \
  | sed -E 's/^\.//' \
  | sed -E 's/(.*)/"\1"/' \
  | sed -E 's/(.*)/\%attr(-,-,-) \1/' \
  | grep -v '""' \
  | grep -iv "/bin/" \
  > $RPM_BUILD_DIR/file.list.%{name}

find . -type f \
  | sed -E 's/^\.//' \
  | sed -E 's/(.*)/"\1"/' \
  | sed -E 's/(.*)/\%attr(755,-,-) \1/' \
  | grep -v '""' \
  | grep -i "/bin/" \
  >> $RPM_BUILD_DIR/file.list.%{name}

find . -type d \
  | sed -E 's/^\.//' \
  | sed -E 's/(.*)/"\1"/' \
  | sed -E 's/(.*)/\%attr(755,-,-) %dir \1/' \
  | grep -v '""' \
  >> $RPM_BUILD_DIR/file.list.%{name}
# End manifest

%post
if [ ! -x /usr/bin/wp ]; then
	ln -s /opt/wp-cli/bin/wp /usr/bin/wp
fi
chown root.root /usr/bin/wp
chmod 755 /usr/bin/wp

%preun
rm -f /usr/bin/wp

%clean
%{__rm} -rf %{buildroot}

%files -f file.list.%{name}
%defattr(644, root, root)

%changelog
* Sat Jan 18 2014 Kurt Payne <kpayne@godaddy.com>
- Initial RPM release.
