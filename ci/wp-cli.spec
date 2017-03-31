Summary: WordPress CLI interface
Name: wp-cli
Version: VERSION
Release: RELEASE
License: MIT
Group: Development/Libraries
Source0: /rpmbuild/SOURCES/wp
BuildRoot: %{_tmppath}/%{name}-%{version}-root-%(%{__id_u} -n)
URL: http://wp-cli.org
AutoReq: no
BuildArch: noarch

%description
A command line interface for WordPress

%prep

%build

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}/usr/local/bin
install -m 755 %{SOURCE0} %{buildroot}/usr/local/bin/wp

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root)
/usr/local/bin/wp

%changelog

