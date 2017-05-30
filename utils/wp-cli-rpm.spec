%define Source1 /home/`whoami`/rpmbuild/SOURCES/wp.1.gz

Name:		wp-cli
Version:	1.1.0
Release:	1%{?dist}

Summary:	The command line interface for WordPress
Packager:	Murtaza Sarıaltun <murtaza.sarialtun@ozguryazilim.com.tr>
Group:		Applications/Internet
License:	MIT
URL:		http://wp-cli.org/
Source0:	%{name}-%{version}.phar
Source1:	wp.1.gz
BuildArch:      noarch 

Requires:	php >= 5.3.29


%description
 wp-cli is a set of command-line tools for managing
 WordPress installations. You can update plug-ins, set up multi-site
 installs and much more, without using a web browser.


%prep

%build

%install
mkdir -p %{buildroot}/%{_bindir}
mkdir -p %{buildroot}/%{_mandir}
mkdir -p %{buildroot}/%{_mandir}/man1
cp -ar %{SOURCE0} %{buildroot}/%{_bindir}/wp
chmod +x %{buildroot}/%{_bindir}/wp
cp -ar %{Source1} %{buildroot}%{_mandir}/man1/

%files
%attr(0755, root, root) "/usr/bin/wp"
%attr(0755, root, root) %{_bindir}/wp
%attr(0644, root, root) %{_mandir}/man1/wp.1*


%changelog
* Mon Mar 27 2017 Murtaza Sarıaltun <murtaza.sarialtun@ozguryazilim.com.tr> - 1.1.0-1.el7.centos
- First Build
